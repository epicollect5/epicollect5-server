<?php

namespace ec5\Traits\Eloquent;

use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectArchive;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\EntryArchive;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\BranchEntryArchive;

trait Archiver
{
    public function archiveProject($projectId, $projectSlug)
    {
        try {
            //cloning project row (for potential restore, safety net)
            $project = Project::where('id', $projectId)
                ->where('slug', $projectSlug)
                ->first();
            // replicate (duplicate) the data
            $projectArchive = $project->replicate();
            $projectArchive->id = $projectId;
            $projectArchive->created_at = $project->created_at;
            $projectArchive->updated_at = $project->updated_at;
            // make into array for mass assign. 
            $projectArchive = $projectArchive->toArray();
            //create copy to projects_archive table
            ProjectArchive::create($projectArchive);

            //delete original row 
            //(media files are not touched)
            // they could be removed at a later stage by a background script
            $project->delete();

            return true;
        } catch (\Exception $e) {
            \Log::error('Error project deletion', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function archiveEntries($projectId)
    {
        try {
            //move entries
            Entry::where('project_id', $projectId)->chunk(100, function ($rowsToMove) {
                foreach ($rowsToMove as $row) {
                    //todo: checlk the id AUTO_INCREMENT...
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
