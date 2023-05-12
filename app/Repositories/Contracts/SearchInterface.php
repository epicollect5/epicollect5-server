<?php

namespace ec5\Repositories\Contracts;

/**
 * Interface RepositoryInterface, re-inventing the wheel 101
 * 
 */
interface SearchInterface
{

    /**
     * @param array $columns
     * @return mixed
     */
    public function all($columns = array('*'));

    /**
     * @param int $perPage
     * @param int $currentPage
     * @param string $search
     * @param array $options
     * @param array $columns
     * @return mixed
     */
    public function paginate($perPage = 1, $currentPage = 1, $search = '', $options = array(), $columns = array('*'));

    /**
     * @param $id
     * @param array $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'));

    /**
     * @param $field
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findBy($field, $value, $columns = array('*'));

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return mixed
     */
    public function findAllBy($column, $operator = null, $value = null, $boolean = 'and');

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return mixed
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and');
}
