<?php

namespace ec5\DTO;

use ec5\Libraries\Utilities\Arrays;

/*
|--------------------------------------------------------------------------
| Project Definition Model
|--------------------------------------------------------------------------
| A model for the JSON Project Definition
|
*/

class ProjectDefinitionDTO extends ProjectModelBase
{
    /**
     * @param array $data
     */
    public function create(array $data)
    {
        // Retrieve project definition template
        $projectDefinitionStructure = config('epicollect.structures.project_definition');

        // Replace key values from $data into the $projectStructure
        $this->data = Arrays::merge($projectDefinitionStructure, $data);
    }

    /**
     * @return string
     */
    public function getProjectRef(): string
    {
        return $this->data['project']['ref'] ?? '';
    }

    /**
     * @param array $data
     */
    public function updateProjectDetails(array $data)
    {
        foreach ($data as $key => $value) {
            if (isset($this->data['project'][$key])) {
                $this->data['project'][$key] = $value;
            }
        }
    }

    public function getFirstFormRef(): string
    {
        return $this->data['project']['forms'][0]['ref'];
    }

    public function getEntriesLimit($ref): ?int
    {
        return $this->data['project']['entries_limits'][$ref] ?? null;
    }

    /**
     *
     */
    public function clearEntriesLimits()
    {
        $this->data['project']['entries_limits'] = [];
    }

    /**
     * @param $ref
     * @param $limitTo
     */
    public function setEntriesLimit($ref, $limitTo)
    {
        $this->data['project']['entries_limits'][$ref] = $limitTo;
    }
}
