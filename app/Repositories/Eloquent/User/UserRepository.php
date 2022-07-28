<?php
namespace ec5\Repositories\Eloquent\User;

use \ec5\Repositories\Contracts\SearchInterface;

class UserRepository implements SearchInterface
{
    use CreateRepository, UpdateRepository, SearchRepository;

    private $errors = [];

    /**
     * return the errors array
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * return count of errors array
     *
     * @return boolean
     */
    public function hasErrors(){
        return count($this->errors) > 0 ? true : false ;
    }

}