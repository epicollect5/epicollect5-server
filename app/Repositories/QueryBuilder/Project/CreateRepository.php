<?php

namespace ec5\Repositories\QueryBuilder\Project;

use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Base;
use Config;

class CreateRepository extends Base
{

    /**
     * @var int - the project id created after an insert
     */
    protected $projectId = 0;

    /**
     * @param Project $project
     * @return bool
     */
    public function create(Project $project)
    {
        return $this->tryProjectCreate($project);
    }

    /**
     * @param Project $project
     * @return bool
     */
    private function tryProjectCreate(Project $project)
    {
        $done = false;
        $this->startTransaction();

        $projectDefinition = $project->getProjectDefinition();
        $projectExtra = $project->getProjectExtra();
        $projectMapping = $project->getProjectMapping();
        $projectStats = $project->getProjectStats();
        $projectDetails = $project->getProjectDetails();
        $projectRef = $projectDetails['ref'] ?? null;

        if (empty($projectRef)) {
            $this->doRollBack();
            return $done;
        }

        // Insert project details get back insert_id
        $this->projectId = $this->insertReturnId(Config::get('ec5Tables.projects'), $projectDetails);
        if (!$this->projectId) {
            $this->doRollBack();
            return $done;
        }

        // Insert project role
        $projectRole['project_id'] = $this->projectId;
        $projectRole['user_id'] = $projectDetails['created_by'];
        $projectRole['role'] = Config::get('ec5Permissions.projects.creator_role');

        $projectRoleId = $this->insertReturnId(Config::get('ec5Tables.project_roles'), $projectRole);
        if (!$projectRoleId) {
            $this->doRollBack();
            return $done;
        }

        // Insert project stats
        $statsData = $projectStats->getJsonData();
        $statsData['project_id'] = $this->projectId;

        $projectStatId = $this->insertReturnId(Config::get('ec5Tables.project_stats'), $statsData);
        if (!$projectStatId) {
            $this->doRollBack();
            return $done;
        }

        // Insert project JSON structures
        $structureData['project_id'] = $this->projectId;
        $structureData['project_definition'] = $projectDefinition->getJsonData();
        $structureData['project_extra'] = $projectExtra->getJsonData();
        $structureData['project_mapping'] = $projectMapping->getJsonData();

        $structureId = $this->insertReturnId(Config::get('ec5Tables.project_structures'), $structureData);
        if (!$structureId) {
            $this->doRollBack();
            return $done;
        }

        // All good
        $this->doCommit();

        return $this->projectId;
    }

    /**
     * @return int
     */
    public function getProjectId(): int
    {
        return $this->projectId;
    }
}
