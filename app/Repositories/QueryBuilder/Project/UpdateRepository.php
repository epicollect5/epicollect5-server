<?php

namespace ec5\Repositories\QueryBuilder\Project;

use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Base;
use Config;

class UpdateRepository extends Base
{

    /**
     * @param Project $project
     * @param array $projectDetails
     * @param bool $setUpdatedAt
     * @return bool
     */
    public function updateProject(Project $project, array $projectDetails, $setUpdatedAt = false)
    {

        $done = false;

        if (empty($projectDetails)) {
            return $done;
        }

        $this->startTransaction();

        // Insert project details get back insert_id
        // Check, rollback if error
        $doUpdate = $this->updateById(Config::get('ec5Tables.projects'), $project->getId(), $projectDetails);
        $this->LG[] = "update project struct" .  $project->getId()  . "returned  $doUpdate";

        if ($this->hasErrors()) {
            $this->doRollBack();
            return $done;
        }

        $doUpdate = $this->dbUpdateStructure($project, $setUpdatedAt);

        $this->LG[] = "insert project struct" . $project->getId() . "returned  $doUpdate";
        if (!$doUpdate) {
            $this->doRollBack();
            return $done;
        }

        // All good
        $this->doCommit();
        $done = true;
        return $done;
    }

    /**
     * @param Project $project
     * @param $setUpdatedAt
     * @return bool
     */
    public function updateProjectStructure(Project $project, $setUpdatedAt = false)
    {

        $done = false;

        $this->startTransaction();

        // Insert project details get back insert_id
        // Check, rollback if error
        $doUpdate = $this->dbUpdateStructure($project, $setUpdatedAt);
        $this->LG[] = 'insert project structure' . $project->getProjectStructureId() . 'returned $doUpdate';
        if (!$doUpdate) {
            $this->doRollBack();
            return $done;
        }

        // All good
        $this->doCommit();
        $done = true;
        return $done;
    }

//    /**
//     * @param Project $project
//     * @param $setUpdatedAt
//     * @return bool
//     */
//    public function updateCustomMapping(Project $project, $setUpdatedAt = false)
//    {
//
//        $done = false;
//
//        $this->startTransaction();
//
//        // Insert project details get back insert_id
//        // Check, rollback if error
//        $doUpdate = $this->dbUpdateStructure($project, $setUpdatedAt);
//        $this->LG[] = 'insert project structure' . $project->getProjectStructureId() . 'returned $doUpdate';
//        if (!$doUpdate) {
//            $this->doRollBack();
//            return $done;
//        }
//
//        // All good
//        $this->doCommit();
//        $done = true;
//        return $done;
//    }

    /**
     * @param Project $project
     * @param $setUpdatedAt
     * @return bool
     */
    private function dbUpdateStructure(Project $project, $setUpdatedAt)
    {

        $data = [
            'project_definition' => $project->getProjectDefinition()->getJsonData(),
            'project_extra' => $project->getProjectExtra()->getJsonData(),
            'project_mapping' => $project->getProjectMapping()->getJsonData()
        ];

        // Set updated_at field?
        if ($setUpdatedAt) $data['updated_at'] = date('Y-m-d H:i:s');

        $this->updateById(Config::get('ec5Tables.project_structures'), $project->getProjectStructureId(), $data);

        if ($this->hasErrors()) {
            return false;
        }

        return true;
    }

}
