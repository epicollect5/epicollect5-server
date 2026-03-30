<?php

namespace ec5\Http\Controllers\Web\Admin\Tools;

use Auth;
use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Http\Controllers\Controller;
use ec5\Models\Project\Project;
use ec5\Services\Entries\EntriesDownloadService;
use ec5\Services\Mapping\DataMappingService;
use ec5\Services\Mapping\ProjectMappingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Log;
use Throwable;

class BulkExportController extends Controller
{
    public function show()
    {
        return view('admin.bulk_export', ['projects' => []]);
    }

    /**
     * Parse the uploaded CSV, resolve each is_infection=TRUE title to a project
     * row, and return the view with the resolved list so the browser can drive
     * sequential downloads via the download() endpoint.
     */
    public function upload(Request $request)
    {
        // Use ValidatesRequests (via Controller) — avoids the $request->validate() macro
        $this->validate($request, [
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        // Release the file-session lock immediately so concurrent admin requests
        // (e.g. stats AJAX still in flight from the previous page) do not block us.
        session()->save();

        try {
            $handle = fopen($request->file('csv_file')->getRealPath(), 'r');

            // Normalise header row so column order does not matter
            $rawHeaders = fgetcsv($handle);
            if (!$rawHeaders) {
                fclose($handle);
                return back()->withErrors(['csv_file' => 'Could not parse CSV headers.']);
            }

            $headers = array_map('strtolower', array_map('trim', $rawHeaders));
            $titleIdx = array_search('project_title', $headers);
            $infectionIdx = array_search('is_infection', $headers);

            if ($titleIdx === false || $infectionIdx === false) {
                fclose($handle);
                return back()->withErrors(['csv_file' => 'Missing required columns: project_title, is_infection.']);
            }

            // Collect project titles where is_infection is TRUE (case-insensitive)
            $targetTitles = [];
            while (($row = fgetcsv($handle)) !== false) {
                if (!isset($row[$infectionIdx])) {
                    continue;
                }
                if (strtolower(trim($row[$infectionIdx])) === 'true') {
                    $targetTitles[] = trim($row[$titleIdx]);
                }
            }
            fclose($handle);

            if (empty($targetTitles)) {
                return back()->withErrors(['csv_file' => 'No projects with is_infection=TRUE found in the uploaded file.']);
            }

            // Resolve all titles in one query, then map in memory — avoids N+1 with large lists.
            // keyBy with strtolower so the lookup is case-insensitive: MySQL WHERE IN is
            // case-insensitive but PHP collection lookups are not.
            $foundProjects = Project::whereIn('name', $targetTitles)
                ->where('status', '<>', 'archived')
                ->get(['name', 'slug'])
                ->keyBy(fn($p) => strtolower($p->name));

            $projects = [];
            foreach ($targetTitles as $title) {
                $match = $foundProjects->get(strtolower($title));
                $projects[] = [
                    'name'  => $title,
                    'slug'  => $match->slug ?? null,
                    'found' => $match !== null,
                ];
            }

            return view('admin.bulk_export', ['projects' => $projects]);

        } catch (Throwable $e) {
            Log::error('BulkExport upload failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['csv_file' => 'An error occurred while processing the file: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate and stream the CSV ZIP for a single project.
     * Called sequentially by the browser JS loop — one request per project.
     */
    public function download(string $slug)
    {
        // Release session lock immediately — this is a long-running streaming response
        session()->save();

        $projectRow = Project::where('slug', $slug)
            ->where('status', '<>', 'archived')
            ->first();

        if (!$projectRow) {
            abort(404, "Project not found: $slug");
        }

        // Build a fresh ProjectDTO — same pattern as EntryGenerator::initDTOs()
        $projectDTO = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $projectDTO->initAllDTOs(Project::findBySlug($slug));

        $user = Auth::user();
        $diskRoot = config('filesystems.disks.entries_zip.root') . '/';
        $relativeDir = 'bulk-export/' . $user->id . '/' . $projectRow->ref;
        $projectDir = $diskRoot . $relativeDir;

        // Ensure the per-project working directory exists
        $storage = Storage::disk('entries_zip');
        if (!$storage->exists($relativeDir)) {
            $storage->makeDirectory($relativeDir);
        }

        set_time_limit(0);

        $params = ['format' => 'csv', 'map_index' => 0];
        $downloadService = new EntriesDownloadService(new DataMappingService());

        try {
            if (!$downloadService->createArchive($projectDTO, $projectDir, $params)) {
                abort(500, "Archive creation failed for $slug");
            }
        } catch (Throwable $e) {
            Log::error('BulkExport: exception during createArchive', [
                'slug'  => $slug,
                'error' => $e->getMessage(),
            ]);
            abort(500, $e->getMessage());
        }

        $zipName = $slug . '-csv.zip';
        $zipPath = $projectDir . '/' . $zipName;

        if (!file_exists($zipPath)) {
            abort(500, "ZIP not found after archive creation: $slug");
        }

        // Schedule empty directory cleanup after the ZIP is sent and deleted
        register_shutdown_function(function () use ($projectDir) {
            if (is_dir($projectDir)) {
                File::deleteDirectory($projectDir);
            }
        });

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }
}

