<?php

namespace ec5\Repositories\QueryBuilder\OAuth;

use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Base;
use Config;

class CreateRepository extends Base
{

    /**
     * @param $projectId
     * @param $clientId
     * @return bool
     */
    public function createOauthProjectClient($projectId, $clientId)
    {
        $done = false;
        $this->startTransaction();

        // Insert project details get back insert_id
        $oauthProjectClientId = $this->insertReturnId(Config::get('ec5Tables.oauth_client_projects'), [
            'project_id' => $projectId,
            'client_id' => $clientId
        ]);

        if (!$oauthProjectClientId) {
            $this->doRollBack();
            return $done;
        }

        // All good
        $this->doCommit();
        $done = true;
        return $done;
    }

}
