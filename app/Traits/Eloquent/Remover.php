<?php

namespace ec5\Traits\Eloquent;

use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\EntryArchive;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\BranchEntryArchive;
use Illuminate\Support\Facades\Config;

trait Remover
{
    public function removeProject($projectId, $projectSlug): bool
    {
        try {
            $project = Project::where('id', $projectId)
                ->where('slug', $projectSlug)
                ->first();

            $project->delete();

            return true;
        } catch (\Exception $e) {
            \Log::error('Error removeProject()', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}
