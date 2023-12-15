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
        $this->projectsTable = config('epicollect.tables.projects');
        $this->projectsStatsTable = config('epicollect.tables.project_stats');
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
            config('epicollect.tables.project_structures'),
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

    public function findBy($field, $value, $columns = array('*'))
    {
        // TODO: Implement findBy() method.
    }
}
