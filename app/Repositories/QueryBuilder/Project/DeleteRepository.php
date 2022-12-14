<?php

namespace ec5\Repositories\QueryBuilder\Project;

use ec5\Repositories\QueryBuilder\Base;
use ec5\Libraries\DirectoryGenerator\DirectoryGenerator;
use Storage;
use File;
use Config;
use DB;
use Log;

class DeleteRepository extends Base
{
    use DirectoryGenerator;

    /**
     * DeleteRepository constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Delete a project
     * 'ON DELETE CASCADE' will remove this project from all relevant tables
     *
     * @param $projectId
     * @return bool
     */
    public function delete($projectId)
    {

        $this->startTransaction();

        // Remove project
        if (!$this->deleteById(Config::get('ec5Tables.projects'), $projectId)) {
            // Rollback
            $this->doRollBack();
            // Set errors
            $this->errors = ['project_delete' => ['ec5_222']];
            return false;
        }

        // All good
        $this->doCommit();
        return true;
    }

    public function deleteEntries($projectId)
    {
        $entriesTableName = Config::get('ec5Tables.entries');
        $branchEntriesTableName = Config::get('ec5Tables.branch_entries');
        $this->startTransaction();

        try {
            DB::table($entriesTableName)
                ->where('project_id', '=', $projectId)
                ->delete();
        } catch (\Exception $e) {
            Log::error('Entries deletion unsuccessful', [
                'project_id' => $projectId,
                'Exception' => json_encode($e)
            ]);
            $this->doRollBack();
            $this->errors = ['entries_deletion' => ['ec5_336']];
            return false;
        }

        try {
            DB::table($branchEntriesTableName)
                ->where('project_id', '=', $projectId)
                ->delete();
        } catch (\Exception $e) {
            Log::error('Entries deletion unsuccessful', [
                'project_id' => $projectId,
                'Exception' => json_encode($e)
            ]);
            $this->doRollBack();
            $this->errors = ['entries_deletion' => ['ec5_336']];
            return false;
        }

        // All good
        $this->doCommit();
        return true;
    }

    public function deleteProjectMedia($projectRef)
    {
        // List all the drivers we want to delete files from for this project
        $drivers = Config::get('ec5Media.project_deletable');
        $this->deleteMedia($projectRef, $drivers);
    }

    public function deleteEntriesMedia($projectRef)
    {
        // List all the drivers we want to delete files from for the entries
        $drivers = Config::get('ec5Media.entries_deletable');
        $this->deleteMedia($projectRef, $drivers);
    }

    /** Delete media
     * @param $projectRef
     * @param $drivers
     */
    private function deleteMedia($projectRef, $drivers)
    {
        // Loop each driver
        foreach ($drivers as $driver) {

            // Get disk, path prefix and all directories for this driver
            $disk = Storage::disk($driver);
            $pathPrefix = $disk->getDriver()->getAdapter()->getPathPrefix();
            $directories = $this->directoryGenerator($disk);

            // Loop each directory
            foreach ($directories as $directory) {
                // If the directory name matches the project ref, delete
                if ($directory == $projectRef) {
                    // Note: need to use File facade here, as Storage doesn't delete
                    $deleted = File::deleteDirectory($pathPrefix . $directory);
                    // If the delete failed, add error and return (inform user to contact the administrator)
                    if (!$deleted) {
                        $this->errors = ['project_delete_media' => ['ec5_223']];
                        return;
                    }
                }
            }
        }
    }
}
