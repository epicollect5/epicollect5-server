<?php

namespace ec5\Repositories\QueryBuilder\Entry\Archive;

use ec5\Repositories\QueryBuilder\Base;
use ec5\Repositories\QueryBuilder\Entry\Delete\DeleteBase as Delete;

use DB;

abstract class ArchiveBase extends Base
{

    /**
     * @var string
     */
    protected $table = '';

    /**
     * @var Delete
     */
    protected $delete;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * ArchiveBase constructor.
     * @param Delete $delete
     */
    public function __construct(Delete $delete)
    {
        DB::connection()->enableQueryLog();

        $this->delete = $delete;

        parent::__construct();

    }

    /**
     * Archive function to be implemented by child classes
     *
     * @param $projectId
     * @param $formRef
     * @param $entryUuid
     * @param bool $keepOpenTransaction
     * @return mixed
     */
    public abstract function archive($projectId, $formRef, $entryUuid, $keepOpenTransaction = false);

    /**
     * @param $projectId
     * @param $entryUuids - the entry uuids to be copied
     */
    protected function copy($projectId, $entryUuids)
    {

        // Select all entries with a uuid in $uuids array
        DB::table($this->table)
            ->select('*')
            ->where('project_id', '=', $projectId)
            ->whereIn('uuid', $entryUuids)
            ->orderBy('created_at', 'DESC')
            ->chunk(100, function ($data) use ($entryUuids) {
            foreach ($data as $entry) {
                // Update or Insert into the archive table
                if (!$this->updateOrInsert($this->table . '_archive', ['uuid' => $entry->uuid], get_object_vars($entry))) {
                    return;
                }
            }
        });
    }

    /**
     * @param $projectId
     * @param $entryUuids - the entry uuids to be deleted
     */
    protected function delete($projectId, $entryUuids)
    {
        // Delete entries from entries table
        $this->delete->delete($projectId, $entryUuids);
    }
}
