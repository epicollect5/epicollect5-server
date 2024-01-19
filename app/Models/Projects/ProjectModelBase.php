<?php

namespace ec5\Models\Projects;

/*
|--------------------------------------------------------------------------
| Project Model Base
|--------------------------------------------------------------------------
| An abstract model which is extended by other JSON Project Models
|
*/

abstract class ProjectModelBase
{
    /**
     * @var $data
     */
    protected $data;

    /**
     * Initialise from existing data
     *
     * @param $data
     */
    public function init($data)
    {
        // Load array or json decode string into array
        $this->data = is_array($data) ? $data : json_decode($data, true);
    }

    /**
     * Create new from data
     *
     * @param $data
     */
    public function create(array $data)
    {
        //
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getJsonData()
    {
        return json_encode($this->data);
    }

    abstract function updateProjectDetails(array $data);
}
