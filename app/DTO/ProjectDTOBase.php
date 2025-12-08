<?php

namespace ec5\DTO;

/*
|--------------------------------------------------------------------------
| Project DTO Base
|--------------------------------------------------------------------------
| An abstract model which is extended by other JSON Project DTOs
|
*/

abstract class ProjectDTOBase
{
    protected array|string $data;

    /**
     * Initialise from existing data
     */
    public function init(array|string $data): void
    {
        // Load array or json decode string into array
        $this->data = is_array($data) ? $data : json_decode($data, true);
    }

    /**
     * Create new from data
     *
     */
    public function create(array $data)
    {
        //
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData($data): void
    {
        $this->data = $data;
    }

    public function getJsonData(): false|string
    {
        return json_encode($this->data);
    }

    abstract public function updateProjectDetails(array $data);
}
