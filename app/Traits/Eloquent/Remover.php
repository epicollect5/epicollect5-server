<?php

namespace ec5\Traits\Eloquent;

use ec5\Models\Eloquent\Entries\BranchEntry;
use ec5\Models\Eloquent\Entries\Entry;
use ec5\Models\Eloquent\Project;
use Exception;
use File;
use Log;
use Storage;

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

    public function removeEntries($projectId, $projectRef): bool
    {
        try {
            // Delete records from the Entry model in chunks
            Entry::where('project_id', $projectId)->chunk(250, function ($entries) {
                foreach ($entries as $entry) {
                    $entry->delete();
                }
            });

            // Delete records from the BranchEntry model in chunks
            BranchEntry::where('project_id', $projectId)->chunk(250, function ($branchEntries) {
                foreach ($branchEntries as $branchEntry) {
                    $branchEntry->delete();
                }
            });

            //remove all the entries media folders
            $drivers = config('epicollect.media.entries_deletable');

            foreach ($drivers as $driver) {
                // Get disk, path prefix and all directories for this driver
                $disk = Storage::disk($driver);
                $pathPrefix = $disk->getDriver()->getAdapter()->getPathPrefix();
                // \Log::info('delete path ->' . $pathPrefix . $projectRef);
                // Note: need to use File facade here, as Storage doesn't delete
                File::deleteDirectory($pathPrefix . $projectRef);
            }
            return true;
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', [
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }
}

