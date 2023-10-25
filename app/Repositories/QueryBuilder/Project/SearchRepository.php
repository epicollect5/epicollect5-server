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

    public function all($columns = array('*'))
    {
        $query = DB::table($this->projectsTable)
            ->where('status', '<>', 'archived') // Skip rows where status is 'archived'
            ->orderBy('created_at', 'desc');

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
        return DB::table($this->projectsTable)
            ->where('status', '<>', 'archived') // Skip rows where status is 'archived'
            ->where($column, $operator, $value, $boolean)->first();
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
        $query = $query->where('status', '<>', 'archived'); // Skip rows where status is 'archived'

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
            ->where('status', '<>', $trashedStatus)
            ->where('status', '<>', 'archived') // Skip rows where status is 'archived'
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
            ->where('status', '<>', 'archived') // Skip rows where status is 'archived'
            ->take(50);
        return $query->get();
    }

    /**
     * @param $field
     * @param $value
     * @param array $columns
     * @return mixed
     */


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
            ->where('status', '<>', 'archived') // Skip rows where status is 'archived'
            ->select('updated_at');

        return $query->first();
    }

    public function findBy($field, $value, $columns = array('*'))
    {
        // TODO: Implement findBy() method.
    }
}
