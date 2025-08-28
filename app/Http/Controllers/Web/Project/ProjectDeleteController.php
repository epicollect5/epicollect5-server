<?php

namespace ec5\Http\Controllers\Web\Project;

use Aws\S3\Exception\S3Exception;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectFeatured;
use ec5\Models\Project\ProjectStats;
use ec5\Traits\Eloquent\Archiver;
use ec5\Traits\Eloquent\StatsRefresher;
use ec5\Traits\Requests\RequestAttributes;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Log;
use Storage;
use Throwable;

class ProjectDeleteController
{
    use RequestAttributes;
    use Archiver;
    use StatsRefresher;

    /**
     * @throws Throwable
     */
    public function show()
    {
        if (!$this->requestedProjectRole()->canDeleteProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        // If the project IS NOT trashed, redirect to error page
        if ($this->requestedProject()->status !== config('epicollect.strings.project_status.trashed')) {
            return view('errors.gen_error')->withErrors(['view' => 'ec5_11']);
        }

        $this->refreshProjectStats($this->requestedProject());
        return view('project.project_delete');
    }

    /**
     * @throws Throwable
     */
    public function delete(Request $request)
    {
        $payload = $request->all();
        $projectSlug = $this->requestedProject()->slug;

        //if missing project name, bail out
        if (empty($payload['project-name'])) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')->withErrors(['ec5_103']);
        }
        $projectId = $this->requestedProject()->getId();
        $projectName = Project::where('id', $projectId)->first()->name;

        //if the project name does not match, bail out
        if ($projectName !== $payload['project-name']) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')->withErrors(['ec5_21']);
        }
        //no permission to delete, bail out
        if (!$this->requestedProjectRole()->canDeleteProject()) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')->withErrors(['ec5_91']);
        }
        // Check if this project is featured, cannot be deleted
        if (ProjectFeatured::where('project_id', $projectId)->exists()) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')->withErrors(['ec5_221']);
        }

        $projectStat = ProjectStats::where('project_id', $projectId)->first();
        if ($projectStat->total_entries === 0) {
            if ($this->hardDelete($projectId, $projectSlug)) {
                return redirect('myprojects')->with('message', 'ec5_114');
            } else {
                return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')
                    ->withErrors(['ec5_104']);
            }
        } else {
            //if the project has entries, soft delete it (set as archived as well)
            if ($this->softDelete($projectId, $projectSlug)) {
                return redirect('myprojects')->with('message', 'ec5_114');
            } else {
                return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')
                    ->withErrors(['ec5_104']);
            }
        }
    }

    /*
    Soft delete a project by setting its status to archived
    Entries and branch entries are not touched.
    Media files for the project are not touched, they can be removed at a later stage
    since deleting lots of rows and files is an expensive operation
    and the user is waiting.
    The following tables have a FK to the project table with ON CASCADE DELETE:
    - project_structures,
    - project_stats,
    - project_roles,
    - oauth_client_projects,
    - project_datasets

    None of them get touched
    */
    /**
     * @throws Throwable
     */
    public function softDelete($projectId, $projectSlug)
    {
        return $this->archiveProject($projectId, $projectSlug);
    }

    /**
     * @param $projectId
     * @param $projectSlug
     * @return bool
     * @throws Throwable
     * @throws Exception
     */
    public function hardDelete($projectId, $projectSlug)
    {
        try {
            DB::beginTransaction();
            //project must have trashed status
            $trashedStatus = config('epicollect.strings.project_status.trashed');
            $project = Project::where('id', $projectId)
                ->where('slug', $projectSlug)
                ->where('status', $trashedStatus)
                ->first(); // Add ->first() to get the model instance

            //delete project from db
            if ($project && $project->delete()) {
                //delete project logo
                if ($this->removeProjectLogo($project->ref)) {
                    DB::commit();
                    return true;
                } else {
                    DB::rollBack();
                    return false;
                }
            } else {
                DB::rollBack();
                return false;
            }
        } catch (Throwable $e) {
            Log::error('hardDelete() project failure', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    /**
     * @throws Exception
     */
    private function removeProjectLogo($projectRef)
    {
        $disk = config('epicollect.media.project_avatar.disk');
        $filename = config('epicollect.media.project_avatar.filename');
        $path = $projectRef . '/' . $filename;

        // Resolve base path (local root or S3 bucket URL)
        $root = config("filesystems.disks.$disk.root");
        $fullPath = $root ? $root . '/' . $path : $path;

        Log::debug('Attempting to delete project logo', [
            'disk'      => $disk,
            'relative'  => $path,
            'full_path' => $fullPath
        ]);

        if (config("filesystems.default") === 's3') {
            $maxRetries = 3;
            $retryDelay = 1; // seconds

            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                try {
                    return Storage::disk($disk)->deleteDirectory($projectRef);
                } catch (Throwable $e) {
                    if ($retry === $maxRetries || !($e instanceof S3Exception && Common::isRetryableError($e))) {
                        Log::error('Cannot delete project logo S3', ['exception' => $e->getMessage()]);
                        return false;
                    }
                    sleep($retryDelay * pow(2, $retry)); // Exponential backoff
                }
            }
        }

        if (config("filesystems.default") === 'local') {
            try {
                return Storage::disk($disk)->deleteDirectory($projectRef);
            } catch (Throwable $e) {
                Log::error('Cannot delete project logo local', ['exception' => $e->getMessage()]);
                return false;
            }
        }

        return true;
    }
}
