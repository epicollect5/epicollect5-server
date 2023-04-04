<?php

namespace ec5\Repositories\QueryBuilder\Project;

use ec5\Repositories\Contracts\SearchInterface as RepositoryInterface;

use DB;
use Config;

class SearchRepository implements RepositoryInterface
{
    private $projectsTable;
    private $projectsStatsTable;

    public function __construct()
    {
        $this->projectsTable = Config::get('ec5Tables.projects');
        $this->projectsStatsTable = Config::get('ec5Tables.project_stats');
    }

    /**
     * @param $perPage
     * @param $userId
     * @param $options
     * @param array $columns
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function myProjects($perPage, $userId, $options, $columns = ['*'])
    {
        $query = DB::table($this->projectsTable)
            ->leftJoin(Config::get('ec5Tables.project_roles'), 'projects.id', '=', 'project_roles.project_id')
            ->where('project_roles.user_id', $userId)
            ->where(function ($query) use ($options) {
                // If we have filter criteria, add to where clause
                if (!empty($options['filter_type']) && !empty($options['filter_value'])) {
                    $query->where($options['filter_type'], '=', $options['filter_value']);
                }
            })
            ->where(function ($query) use ($options) {
                // If we have filter criteria, add to where clause
                if (!empty($options['search'])) {
                    $query->where('name', 'LIKE', '%' . $options['search'] . '%');
                }
            })
            ->orderBy('created_at', 'desc')
            ->select($columns);

        return $query->simplePaginate($perPage);
    }

    /**
     * @param $category
     * @param array $columns
     * @param array $parameters
     * @return array|static[]
     */
    public function publicAndListed($category = null, $columns = ['*'], $parameters = [])
    {
        /** imp:
         * total_entries from project_stats could be not the latest value.
         * For perfomances reasons, we update the stats only when a project is
         * accessed via the web browser (dataviewer) or the project is exported via the api
         * 
         * a precise total_entries is not essential for the projects list
         * 
         */
        $columns[] = 'total_entries';
        $trashedStatus = Config::get('ec5Strings.project_status.trashed');
        $publicAccess = Config::get('ec5Strings.project_access.public');
        $listedVisibility = Config::get('ec5Strings.project_visibility.listed');
        $projectsPerPage = Config::get('ec5Limits.projects_per_page');
        $sortBy = Config::get('ec5Enums.search_projects_defaults.sort_by');
        $sortOrder = Config::get('ec5Enums.search_projects_defaults.sort_order');

        $query = DB::table($this->projectsTable)
            ->join(
                $this->projectsStatsTable,
                $this->projectsTable . '.id',
                '=',
                $this->projectsStatsTable . '.project_id'
            )
            // Status not trashed
            ->where('status', '<>', $trashedStatus)
            // Public access only
            ->where('access', $publicAccess)
            // Listed visibility
            ->where('visibility', $listedVisibility)
            //search by name if a string is passed in
            ->where(function ($query) use ($parameters) {
                // If we have filter criteria, add to where clause
                if (!empty($parameters['name'])) {
                    // 'Like' search term
                    $query->where('name', 'LIKE', '%' . $parameters['name'] . '%');
                }
            })
            ->select($columns);

        //filter by category if it is passed in
        if ($category) {
            //todo check if category is valid??? in controller
            $query->where('category', $category);
        }

        if (!empty($parameters['sort_by'])) {
            //todo check if sort by is valid????? do in controller
            $sortBy = $parameters['sort_by'];
        }
        //default is desc, check if we need asc
        if (!empty($parameters['sort_order'])) {
            $sortOrder = $parameters['sort_order'];
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->simplePaginate($projectsPerPage);
    }

    /**
     * @param int $perPage
     * @param array $options
     * @param array $columns
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function adminProjects($perPage, $options = array(), $columns = ['*'])
    {

        /** imp:
         * total_entries from project_stats could not be the latest value.
         * For perfomances reasons, we update the stats only when a project is
         * accessed via the web browser or its entries are exported via the api
         * 
         * a precise total_entries is not essential for the projects list
         * 
         */

        $columns = ['*', $this->projectsTable . '.id as project_id'];
        $query = DB::table($this->projectsTable)
            ->distinct()
            ->join(
                $this->projectsStatsTable,
                $this->projectsTable . '.id',
                '=',
                $this->projectsStatsTable . '.project_id'
            )
            ->where(function ($query) use ($options) {
                // If we have filter criteria, add to where clause
                if (!empty($options['name'])) {
                    // 'Like' search term
                    $query->where('name', 'LIKE', '%' . $options['name'] . '%');
                }
            })
            ->where(function ($query) use ($options) {
                // If we have access filter, add it
                if (!empty($options['access'])) {
                    $query->where('access', '=', $options['access']);
                }
            })
            ->where(function ($query) use ($options) {
                // If we have visibility filter, add it
                if (!empty($options['visibility'])) {
                    $query->where('visibility', '=', $options['visibility']);
                }
            })
            ->orderBy('total_entries', 'desc')
            ->select($columns);

        return $query->simplePaginate($perPage);
    }

    /**
     * @param $projectId
     * @return bool
     */
    public function isFeatured($projectId)
    {
        $project =
            DB::table(Config::get('ec5Tables.projects_featured'))
            ->where('project_id', '=', $projectId)
            ->count();

        return $project > 0;
    }

    /**
     * @param array $columns
     */
    public function featuredProjects($columns = ['*'])
    {
        $query = DB::table($this->projectsTable)
            ->join(
                Config::get('ec5Tables.projects_featured'),
                $this->projectsTable . '.id',
                '=',
                Config::get('ec5Tables.projects_featured') . '.project_id'
            )
            ->orderBy('projects_featured.id', 'asc')
            ->select($columns);

        return $query->get();
    }

    /**
     * @param array $columns
     * @return array
     */
    public function all($columns = array('*'))
    {
        $query = DB::table($this->projectsTable)->orderBy('created_at', 'desc');

        return $query->get();
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
        return DB::table($this->projectsTable)->where($column, $operator, $value, $boolean)->first();
    }

    /**
     * @param int $perPage
     * @param int $currentPage
     * @param string $search
     * @param array $options
     * @param array $columns
     * @return null
     */
    public function paginate($perPage = 1, $currentPage = 1, $search = '', $options = array(), $columns = array('*'))
    {
        return null;
    }

    /**
     * @param $slug
     * @param array $columns
     * @return mixed
     */
    public function find($slug, $columns = array('*'))
    {
        $query = DB::table($this->projectsTable);
        $query = $query->leftJoin(
            $this->projectsStatsTable,
            'projects.id',
            '=',
            'project_stats.project_id'
        );

        $query = $query->leftJoin(
            Config::get('ec5Tables.project_structures'),
            'projects.id',
            '=',
            'project_structures.project_id'
        );

        $query = $query->where('projects.slug', $slug);
        $query = $query->select(
            'projects.*',
            'project_stats.id AS stats_id',
            'project_stats.*',
            'project_structures.*',
            'project_structures.updated_at as structure_last_updated',
            'project_structures.id as structure_id'
        );

        return $query->first();
    }


    /**
     * Return all the projects which starts the string passed in the name
     * it is used by the mobile app project search
     * limit to 50 to keep it responsive on the mobile app
     * Order by updated_at to list the latest and most active projects first
     * Remember: project names are unique!
     *
     * @param $name
     * @param array $columns
     * @return mixed
     */
    /**
     * @param $name
     * @param array $columns
     * @return mixed
     */
    public function startsWith($name, $columns = array('*'))
    {

        $trashedStatus = Config::get('ec5Strings.project_status.trashed');
        $query = DB::table($this->projectsTable)
            ->select($columns)
            ->where('name', 'like', $name . '%')
            //ignore trashed projects
            ->where('status', '<>',  $trashedStatus)
            ->orderBy('updated_at', 'desc') //most recent/active first
            ->take(50); //we should stay within this limit as names are unique
        return $query->get();
    }

    /**
     * Return all the projects which contain the string passed in the name
     * it is used by the mobile app project search
     * limit to 50 to keep it responsive on the mobile app
     *
     * @param $name
     * @param array $columns
     * @return mixed
     */
    public function like($name, $columns = array('*'))
    {
        $query = DB::table($this->projectsTable)
            ->select($columns)
            ->where('name', 'like', '%' . $name . '%')
            ->take(50);
        return $query->get();
    }

    /**
     * @param $field
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findBy($field, $value, $columns = array('*'))
    {

        //
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
        return null;
    }

    /**
     * @param $projectId
     * @return mixed
     */
    public function version($projectId)
    {
        $query = DB::table(Config::get('ec5Tables.project_structures'))
            ->where('project_id', '=', $projectId)
            ->select('updated_at');

        return $query->first();
    }
}
