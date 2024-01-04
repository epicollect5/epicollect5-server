<?php

namespace ec5\Traits\Eloquent;

use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\EntryArchive;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\BranchEntryArchive;
use Exception;
use File;
use Illuminate\Support\Facades\Config;
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
            //remove all entries amnd branch entries
            Entry::where('project_id', $projectId)->delete();
            BranchEntry::where('project_id', $projectId)->delete();

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

