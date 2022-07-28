<?php namespace ec5\Repositories\QueryBuilder\ProjectRole;

use ec5\Repositories\QueryBuilder\Base;

class DeleteRepository extends Base {

    /**
     * @var SearchRepository $searchRepository
     */
    protected $searchRepository;

    /**
     * CreateRepository constructor.
     * @param SearchRepository $searchRepository
     */
    public function __construct(SearchRepository $searchRepository)
    {
        $this->searchRepository = $searchRepository;
        parent::__construct();
    }
    
    /**
     * Remove a role for a user on a project
     *
     * @param $userId
     * @param $projectId
     * @return bool
     */
    public function delete($userId, $projectId)
    {
        $projectRoleId = $this->searchRepository->getId($userId, $projectId);

        // We found a user project role, try and remove
        if ($projectRoleId) {
            return $this->tryProjectRoleDelete($projectRoleId);
        }

        // Otherwise just return true, we have no user project role
        return true;
    }

    /**
     * Remove a role for a user on a project
     *
     * @param $userId
     * @param $projectId
     * @return bool
     */
    public function deleteAllRoles($userId, $projectId)
    {
        $projectRoleId = $this->searchRepository->getId($userId, $projectId);

        // We found a user project role, try and remove
        if ($projectRoleId) {
            return $this->tryProjectRoleDelete($projectRoleId);
        }

        // Otherwise just return true, we have no user project role
        return true;
    }

    /**
     * @param $projectRoleId
     * @return bool
     */
    private function tryProjectRoleDelete($projectRoleId)
    {

        $this->startTransaction();

        $done = $this->deleteById('project_roles', $projectRoleId);

        if( ! $done ){
            // Rollback
            $this->doRollBack();
            return $done;
        }

        // All good
        $this->doCommit();
        return $done;

    }

}