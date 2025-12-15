<?php

namespace ec5\Traits\Project;

use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Traits\Eloquent\Remover;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

trait ProjectWiper
{
    use Remover;

    /**
     * Fully erase a project:
     *   1. Delete media (chunked)
     *   2. Delete entries (chunked)
     *   3. Delete project + metadata
     *
     * @param int $projectId
     * @param string $projectSlug
     * @param ProgressBar|null $progress
     *
     * @return bool
     * @throws Exception|Throwable
     */
    public function eraseProject(int $projectId, string $projectSlug, ProgressBar $progress = null): bool
    {
        $project = Project::where('id', $projectId)
            ->where('slug', $projectSlug)
            ->first();

        if (!$project) {
            throw new Exception("Project not found.");
        }

        if ($project->status !== 'archived') {
            throw new Exception("Project must be archived before erasing.");
        }

        $projectRef = $project->ref;

        Log::info("[Eraser] Starting erase for project $projectRef ($projectId)");

        /*
        |--------------------------------------------------------------------------
        | 1. Delete Media (Chunked)
        |--------------------------------------------------------------------------
        */
        if ($progress) {
            $progress->setMessage("Deleting media...");
        }

        while (true) {
            $deleted = $this->removeMediaChunk($projectRef, $projectId);

            if ($deleted > 0 && $progress) {
                $progress->advance($deleted);
            }

            if ($deleted === 0) {
                break;
            }
        }

        Log::info("[Eraser] Media deletion complete for project $projectRef");

        /*
        |--------------------------------------------------------------------------
        | 2. Delete Entries (Chunked)
        |--------------------------------------------------------------------------
        */
        if ($progress) {
            $progress->setMessage("Deleting entries...");
        }

        while (true) {
            $this->removeEntriesChunk($projectId);

            $remaining = Entry::where('project_id', $projectId)->count();

            if ($progress) {
                $progress->advance(config('epicollect.setup.bulk_deletion.chunk_size_entries'));
            }

            if ($remaining === 0) {
                break;
            }
        }

        Log::info("[Eraser] Entries deletion complete for project {$projectRef}");

        /*
        |--------------------------------------------------------------------------
        | 3. Delete the project row
        |--------------------------------------------------------------------------
        */
        if ($progress) {
            $progress->setMessage("Removing project data...");
        }

        $this->removeProject($projectId, $projectSlug);

        if ($progress) {
            $progress->advance();
            $progress->finish();
        }

        Log::info("[Eraser] Finished erasing project {$projectRef}");

        return true;
    }

    /**
     * Get the *original* name from project_structures.project_definition JSON.
     */
    public function getOriginalProjectName(int $projectId): ?string
    {
        $row = DB::table('project_structures')
            ->where('project_id', $projectId)
            ->select('project_definition')
            ->first();

        if (!$row || !$row->project_definition) {
            return null;
        }

        $json = json_decode($row->project_definition, true);

        return $json['project']['name'] ?? null;
    }
}
