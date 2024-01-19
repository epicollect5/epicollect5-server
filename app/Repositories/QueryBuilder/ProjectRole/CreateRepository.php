<?php namespace ec5\Repositories\QueryBuilder\ProjectRole;

use ec5\Repositories\QueryBuilder\Base;

class CreateRepository extends Base
{

    /**
     * @var SearchRepository $searchRepository
     */
    protected $searchRepository;

    /**
     * @var DeleteRepository $deleteRepository
     */
    protected $deleteRepository;

    /**
     * CreateRepository constructor.
     * @param SearchRepository $searchRepository
     * @param DeleteRepository $deleteRepository
     */
    public function __construct(SearchRepository $searchRepository, DeleteRepository $deleteRepository)
    {
        $this->searchRepository = $searchRepository;
        $this->deleteRepository = $deleteRepository;
        parent::__construct();
    }

    /**
     * Add a role for a user on a project
     *
     * @param $userId
     * @param $projectId
     * @param $role
     * @return bool
     */
    public function create($userId, $projectId, $role)
    {
        // Try and insert
        return $this->tryProjectRoleCreate($userId, $projectId, $role);
    }

    /**
     * @param $userId
     * @param $projectId
     * @param $role
     * @return bool
     */
    private function tryProjectRoleCreate($userId, $projectId, $role)
    {
        // Remove any existing roles
        $done = $this->deleteRepository->delete($userId, $projectId);

        // If delete failed, return
        if (!$done) {
            return $done;
        }

        // Populate $data array
        $data = ['project_id' => $projectId, 'user_id' => $userId, 'role' => $role];

        $this->startTransaction();

        // Attempt to insert
        $projectRoleId = $this->insertReturnId('project_roles', $data);

        if (!$projectRoleId) {
            // Rollback
            $this->doRollBack();
            return $done;
        }

        // All good
        $this->doCommit();
        $done = true;
        return $done;

    }

    /**
     * Copy over the users from one project to another
     *
     * @param $projectIdFrom
     * @param $projectIdTo
     * @return bool
     */
    public function cloneProjectRoles($projectIdFrom, $projectIdTo)
    {
        $projectRoles = $this->searchRepository->findAllBy('project_id', '=', $projectIdFrom);

        foreach ($projectRoles as $projectRole) {
            // Try and insert
            if (!$this->tryProjectRoleCreate($projectRole->user_id, $projectIdTo, $projectRole->role)) {
                return false;
            }
        }
        return true;
    }

}