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
        if (!$this->deleteById(config('epicollect.tables.projects'), $projectId)) {
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
        $entriesTableName = config('epicollect.tables.entries');
        $branchEntriesTableName = config('epicollect.tables.branch_entries');
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
            $this->errors = ['entries_deletion' => ['ec5_104']];
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
            $this->errors = ['entries_deletion' => ['ec5_104']];
            return false;
        }

        // All good
        $this->doCommit();
        return true;
    }

    public function deleteProjectMedia($projectRef)
    {
        // List all the drivers we want to delete files from for this project
        $drivers = config('epicollect.media.project_deletable');
        $this->deleteMedia($projectRef, $drivers);
    }

    public function deleteEntriesMedia($projectRef)
    {
        // List all the drivers we want to delete files from for the entries
        $drivers = config('epicollect.media.entries_deletable');
        $this->deleteMedia($projectRef, $drivers);
    }

    /** Delete media
     * @param $projectRef
     * @param $drivers
     */
    private function deleteMedia($projectRef, $drivers)
    {
        // Loop each driver (folder) and delete it
        try {
            foreach ($drivers as $driver) {
                // Get disk, path prefix and all directories for this driver
                $disk = Storage::disk($driver);
                $pathPrefix = $disk->getDriver()->getAdapter()->getPathPrefix();
                // \Log::info('delete path ->' . $pathPrefix . $projectRef);
                // Note: need to use File facade here, as Storage doesn't delete
                File::deleteDirectory($pathPrefix . $projectRef);
            }
        } catch (Exception $e) {
            \Log::error('Error deleting media folder ->' . $pathPrefix . $projectRef, [
                'exception' => $e->getMessage()
            ]);
            $this->errors = ['project_delete_media' => ['ec5_223']];
            return;
        }
    }
}
