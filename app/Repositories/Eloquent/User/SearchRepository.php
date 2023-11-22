<?php namespace ec5\Repositories\Eloquent\User;

use ec5\Models\Eloquent\User;
use Auth;

trait SearchRepository
{

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
     */
    public function findAllBy($column, $operator = null, $value = null, $boolean = 'and')
    {
        //
    }


    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return User::where($column, $operator, $value, $boolean)->first();
    }

    /**
     * @return User
     */
    public function user()
    {
        return Auth::user();
    }

    /**
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($columns = array('*'))
    {
        return User::all($columns);
    }

    /**
     * @param $id
     * @param $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        return User::find($id, $columns);
    }

    public function paginate($perPage = 1, $currentPage = 1, $search = '', $options = array(), $columns = array('*'))
    {
        //todo: to be removed
    }
}