<?php

namespace ec5\Traits\Eloquent;

use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectArchive;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\EntryArchive;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\BranchEntryArchive;
use Illuminate\Support\Facades\Config;

trait Archiver
{
    public function archiveProject($projectId, $projectSlug): bool
    {
        try {
            $project = Project::where('id', $projectId)
                ->where('slug', $projectSlug)
                ->first();

            $project->status = Config::get('ec5Strings.project_status.archived');
            // Save the model to persist the changes
            $project->save();
            return true;
        } catch (\Exception $e) {
            \Log::error('Error project archive', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function archiveEntries($projectId): bool
    {
        try {
            //move entries
            Entry::where('project_id', $projectId)->chunk(100, function ($rowsToMove) {
                foreach ($rowsToMove as $row) {
                    //todo: check the id AUTO_INCREMENT...
                    $rowToArchive = $row->replicate();
                    // make into array for mass assign. 
                    $rowToArchive = $rowToArchive->toArray();
                    //create copy to projects_archive table
                    EntryArchive::create($rowToArchive);
                }
            });

            //move branch entries as well
            BranchEntry::where('project_id', $projectId)->chunk(100, function ($rowsToMove) {
                foreach ($rowsToMove as $row) {
                    //todo: check the id AUTO_INCREMENT...
                    $rowToArchive = $row->replicate();
                    // make into array for mass assign. 
                    $rowToArchive = $rowToArchive->toArray();
                    //create copy to projects_archive table
                    BranchEntryArchive::create($rowToArchive);
                }
            });

            // All rows have been successfully moved, so you can proceed with deleting the original rows
            Entry::where('project_id', $projectId)->delete();
            BranchEntry::where('project_id', $projectId)->delete();

            return true;
        } catch (\Exception $e) {
            \Log::error('Error soft deleting project entries', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}
