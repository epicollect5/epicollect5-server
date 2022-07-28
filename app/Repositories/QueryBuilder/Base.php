<?php

namespace ec5\Repositories\QueryBuilder;

use ec5\Libraries\EC5Logger\EC5Logger;
use DB;
use Config;

class Base
{

    protected $errors = [];
    protected $input_special_types = array();

    public function __construct()
    {
        DB::connection()->enableQueryLog();
        $this->input_special_types = Config::get('ec5Enums.input_special_types');
    }

    /**
     * Return the errors array
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Check if has errors
     *
     * @return boolean
     */
    public function hasErrors()
    {
        return count($this->errors) > 0 ? true : false;
    }

    /**
     * Start the transaction
     *
     */
    protected function startTransaction()
    {
        DB::beginTransaction();
    }

    /**
     * Rollback a transaction
     *
     */
    protected function doRollBack()
    {
        DB::rollBack();
    }

    /**
     * Commit a transaction
     *
     */
    protected function doCommit()
    {
        DB::commit();
    }

    /**
     * Insert and return the insert id
     *
     * @param string $tableName
     * @param array $data
     * @return integer
     */
    protected function insertReturnId($tableName, array $data)
    {
        $insertId = 0;

        try {
            $insertId = DB::table($tableName)->insertGetId($data);
            return $insertId;

        } catch (\Exception $e) {
            EC5Logger::error('Insert unsuccessful', null, [json_encode($e)]);
            $this->errors['repository_base'] = ['ec5_45'];
            return $insertId;
        }

    }

    /**
     * updateOrInsert an entry, based on attributes
     *
     * @param $tableName
     * @param array $attributes
     * @param array $data
     * @return bool
     */
    protected function updateOrInsert($tableName, array $attributes, array $data)
    {

        try {
            return DB::table($tableName)->updateOrInsert($attributes, $data);
        } catch (\Exception $e) {
            EC5Logger::error('Update unsuccessful', null, [json_encode($e)]);
            $this->errors['repository_base'] = ['ec5_45'];
            return false;
        }

    }

    /**
     * Update and return the update id
     *
     * @param $tableName
     * @param $id
     * @param array $data
     * @return int
     */
    protected function updateById($tableName, $id, array $data)
    {
        $done = 0;
        try {

            $done = DB::table($tableName)->where('id', $id)->update($data);
            return $done;

        } catch (\Exception $e) {
            EC5Logger::error('Update unsuccessful', null, [json_encode($e)]);
            $this->errors['repository_base'] = ['ec5_45'];
            return $done;
        }

    }

    /**
     * Attempt to delete an entry in the database given the supplied db id
     *
     * @param $tableName
     * @param $id
     * @return bool
     */
    protected function deleteById($tableName, $id)
    {

        try {
            DB::table($tableName)
                ->where('id', '=', $id)
                ->delete();

            return true;

        } catch (\Exception $e) {
            EC5Logger::error('Delete unsuccessful', null, [json_encode($e)]);
            $this->errors[$e->getMessage()] = ['ec5_45'];
            return false;
        }
    }
}
