<?php

namespace ec5\Repositories\QueryBuilder\OAuth;

use DB;
use Config;

class SearchRepository
{

    /**
     * @param $projectId
     * @param array $columns
     * @return mixed
     */
    public function projectApps($projectId, $columns = ['*'])
    {
        $query = DB::table(Config::get('ec5Tables.oauth_client_projects'))
            ->join(Config::get('ec5Tables.oauth_clients'), 'oauth_client_projects.client_id', '=', 'oauth_clients.id')
            ->where('oauth_client_projects.project_id', '=', $projectId)
            ->select($columns);

        return $query->get();
    }

    /**
     * @param $clientId
     * @param $projectId
     * @return mixed
     */
    public function exists($clientId, $projectId)
    {
        $query = DB::table(Config::get('ec5Tables.oauth_client_projects'))
            ->where('project_id', '=', $projectId)
            ->where('client_id', '=', $clientId)
            ->select('*');

        return $query->first();
    }


}