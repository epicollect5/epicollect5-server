<?php

namespace ec5\Models\Projects;

use Config;

/*
|--------------------------------------------------------------------------
| Project Definition Model
|--------------------------------------------------------------------------
| A model for the JSON Project Definition
|
*/

class ProjectDefinition extends ProjectModelBase
{
    /**
     * @param array $data
     */
    public function create(array $data)
    {
        // Retrieve project definition template
        $projectDefinitionStructure = Config::get('ec5ProjectStructures.project_definition');

        // Replace key values from $data into the $projectStructure
        $this->data = $this->mergeArrays($projectDefinitionStructure, $data, []);
    }

    /**
     * @return string
     */
    public function getProjectRef()
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

    /**
     * @return string
     */
    public function getFirstFormRef()
    {
        return $this->data['project']['forms'][0]['ref'];
    }

    /**
     * @param $ref
     * @return int
     */
    public function getEntriesLimit($ref)
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
