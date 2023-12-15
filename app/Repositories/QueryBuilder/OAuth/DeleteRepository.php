<?php

namespace ec5\Repositories\QueryBuilder\OAuth;

use ec5\Repositories\QueryBuilder\Base;
use Config;
use DB;

class DeleteRepository extends Base
{

    protected $table = '';

    /**
     * ArchiveBase constructor.
     */
    public function __construct()
    {
        DB::connection()->enableQueryLog();

        parent::__construct();
    }

    /**
     * @param $projectId
     * @param $clientId
     * @return bool
     */
    public function delete($projectId, $clientId)
    {
        try {
            // Delete client
            DB::table(config('epicollect.tables.oauth_clients'))
                ->where('id', '=', $clientId)
                ->delete();

            // Delete project client
            DB::table(config('epicollect.tables.oauth_client_projects'))
                ->where('client_id', '=', $clientId)
                ->where('project_id', '=', $projectId)
                ->delete();

            return true;

        } catch (\Exception $e) {
            $this->errors['oauth_delete'] = ['ec5_240'];
            return false;
        }

    }

    /**
     * @param $clientId
     * @return bool
     */
    public function revokeTokens($clientId)
    {
        try {
            // Delete client access tokens
            DB::table(config('epicollect.tables.oauth_access_tokens'))
                ->where('client_id', '=', $clientId)
                ->delete();

            return true;

        } catch (\Exception $e) {
            $this->errors['oauth_revoke'] = ['ec5_240'];
            return false;
        }

    }

}