<?php

namespace ec5\Repositories\QueryBuilder\ProjectRole;

use ec5\Repositories\QueryBuilder\Project\SearchRepository as ProjectSearch;
use ec5\Repositories\Eloquent\User\UserRepository;
use ec5\Repositories\Contracts\SearchInterface;

use ec5\Models\ProjectRoles\ProjectRole;
use ec5\Models\Users\User;

use DB;

class SearchRepository implements SearchInterface
{
    /**
     * @var UserRepository Object $userRepository
     */
    protected $userRepository;

    /**
     * @var ProjectSearch $projectSearch
     */
    protected $projectSearch;

    /**
     * @var ProjectRole $projectRole
     */
    protected $projectRole;

    /**
     * @var Array
     */
    protected $errors = [];

    /**
     * @param UserRepository $userRepository
     * @param ProjectSearch $projectSearch
     * @param ProjectRole $projectRole
     */
    public function __construct(UserRepository $userRepository, ProjectSearch $projectSearch, ProjectRole $projectRole)
    {
        $this->userRepository = $userRepository;
        $this->projectSearch = $projectSearch;
        $this->projectRole = $projectRole;
    }

    /**
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($columns = array('*'))
    {
        //
    }

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return mixed
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return DB::table('project_roles')->where($column, $operator, $value, $boolean)->first();
    }

    /**
     * @param $field
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findBy($field, $value, $columns = array('*'))
    {
        return null;
    }

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return null
     */
    public function findAllBy($column, $operator = null, $value = null, $boolean = 'and')
    {
        return DB::table('project_roles')->where($column, $operator, $value, $boolean)->get();
    }

    /**
     * @param $id
     * @param $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        return null;
    }

    /**
     * Function for retrieving paginated project users
     * Optional search criteria can be passed through
     * This will either return an array of sets of users
     * indexed 'manager', 'curator', 'collector',
     * or just the users for one set.
     *
     * @param int $perPage
     * @param int $currentPage
     * @param string $search
     * @param array $options
     * @param array $columns
     * @return array
     */
    public function paginate($perPage = 1, $currentPage = 1, $search = '', $options = array(), $columns = array('*'))
    {
        $users = [];

        // loop round and gather users for each set of roles
        foreach ($options['roles'] as $role) {

            // retrieve all project users where role is $role
            $userSet = DB::table('users')
                ->select('users.id', 'users.name', 'users.last_name', 'users.email', 'project_roles.role')
                ->join('project_roles', 'users.id', '=', 'project_roles.user_id')
                ->where('project_roles.project_id', '=', $options['project_id'])
                ->where('project_roles.role', '=', $role)
                ->where(function ($query) use ($search) {
                    // if we have search criteria, add to where clause
                    if (!empty($search)) {
                        $query->where('users.name', 'LIKE',  '%' . $search . '%')
                            ->orWhere('users.email', 'LIKE', '%' . $search . '%');
                    }
                })
                ->orderBy('users.name', 'asc');

            // now paginate users
            // setting the 'page' variable name relative to the $role
            $users[$role] = $userSet->simplePaginate($perPage, ['*'], 'page-' . $role);
        }

        return $users;
    }

    /**
     * Retrieve all users for a project
     *
     * @param $projectId
     * @return array
     */
    public function users($projectId)
    {
        // Get all users belonging to this project
        $projectRoles = DB::table('project_roles')->where('project_id', $projectId)->get();

        $users = array();

        foreach ($projectRoles as $index => $projectRole) {
            $users[$index] = $this->userRepository->find($projectRole->user_id);
            $users[$index]['role'] = $projectRole->role;
        }

        return $users;
    }

    /**
     * Retrieve all projects for a user
     *
     * @param $userId
     * @return array
     */
    public function projects($userId)
    {
        // get all projects this user is a manager for
        $projectRoles = DB::table('project_roles')->where('user_id', $userId)->where('role', '=', 'manager')->get();

        $projects = array();

        foreach ($projectRoles as $projectRole) {
            $projects[] = $this->projectSearch->find($projectRole->projectId);
        }

        return $projects;
    }

    /**
     * Populate and return a ProjectRole object for user/project
     *
     * @param User|null $user
     * @param $projectId
     * @return ProjectRole
     */
    public function getRole(User $user = null, $projectId)
    {
        $role = null;

        // If we have a valid user
        if ($user && $user->id) {
            $projectRole = DB::table('project_roles')
                ->select('role')
                ->where('user_id', '=', $user->id)
                ->where('project_id', '=', $projectId)
                ->first();
            // If a project role is found, set
            if ($projectRole) {
                $role = $projectRole->role;
            }
        }

        // Set the project role and return
        $this->projectRole->setRole($user, $projectId, $role);

        return $this->projectRole;
    }

    /**
     * Check if a role exists for a user on a project
     *
     * @param $userId
     * @param $projectId
     * @return bool
     */
    public function hasARole($userId, $projectId)
    {
        $projectRole = $this->getRole($userId, $projectId);

        return !empty($projectRole->getRole());
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return count($this->errors) > 0;
    }

    /**
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Function to check the project permissions
     * and authentication of a user
     *
     * @param $project
     * @param $userId
     * @return bool
     */
    public function userHasProjectPermission($project, $userId)
    {
        // check user has a role for this project
        if ($this->hasARole($userId, $project->id)) {
            return true;
        }

        $this->errors[$project->slug] = ['ec5_13'];
        return false;
    }

    /**
     * @param $userId
     * @param $projectId
     * @return int
     */
    public function getId($userId, $projectId)
    {

        $projectRole = DB::table('project_roles')
            ->select('id')
            ->where('user_id', '=', $userId)
            ->where('project_id', '=', $projectId)
            ->first();

        if ($projectRole) {
            return $projectRole->id;
        }
    }
}
