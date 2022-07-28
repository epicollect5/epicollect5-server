<?php namespace ec5\Repositories\Eloquent\User;

use ec5\Repositories\Contracts\SearchInterface;
use ec5\Models\Users\User;
use Auth;


trait SearchRepository {

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

    /**
     * Function for retrieving paginated users
     * Optional search/filter criteria can be passed through
     *
     * @param int $perPage
     * @param int $currentPage
     * @param string $search
     * @param array $options
     * @param array $columns
     * @return mixed
     */
    public function paginate($perPage = 1, $currentPage = 1, $search = '', $options = array(), $columns = array('*'))
    {

        // retrieve paginated users relative to the search (on name and email)
        // and filter (if applicable), ordered by name
        $users = User::where(function ($query) use ($search) {
            // if have search criteria, add to where clause
            if (!empty($search)) {
                $query->where('name', 'LIKE', $search . '%')
                    ->orWhere('email', 'LIKE', $search . '%');
            }
        })->where(function ($query) use ($options) {
            // if have filter criteria, add to where clause
            if (!empty($options['filter']) && !empty($options['filter_option'])) {
                $query->where($options['filter'], '=', $options['filter_option']);
            }
        })->orderBy('name', 'asc');


        // check if the current $page requested will be greater than the total number of pages allowed
        // if so, we should set the current page to the previous page
        $totalPages = ceil($users->count() / $perPage);
        if ($currentPage > $totalPages) {
            $currentPage--;
        } else {
            $currentPage = null;
        }

        // now paginate users
        $users = $users->simplePaginate($perPage);

        return $users;

    }

    /**
     * Search for users via email
     * Returned in specific jquery autocomplete format
     *
     * @param $query
     * @return array
     */
    public function searchByEmail($query)
    {

        $users = User::where('email', 'LIKE', $query . '%')->orderBy('name', 'asc')->get();

        if (count($users) > 0) {

            // format into array for use with autocomplete
            foreach ($users as $user) {

                $suggestions['suggestions'][] = ['value' => $user->email, 'data' => $user->email];

            }
        } else {
            $suggestions['suggestions'][] = ['value' => 'No users found.', 'data' => 'No users found.'];
        }

        return $suggestions;

    }

    /**
     * @param $token
     * @return mixed
     */
    public function findByApiToken($token)
    {
        return User::where('api_token', '=', $token)->first();
    }


}