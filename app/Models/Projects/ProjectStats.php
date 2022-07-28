<?php

namespace ec5\Models\Projects;

use ReflectionClass;
use ReflectionProperty;

/*
|--------------------------------------------------------------------------
| Project Stats Model
|--------------------------------------------------------------------------
| A model for the JSON Project Stats
|
*/

class ProjectStats extends ProjectModelBase
{
    protected $structure_last_updated = '';

    // Own public properties, reflecting those in 'project_stats' db table
    public $total_entries = 0;
    public $total_users = 0;
    public $form_counts = [];
    public $branch_counts = [];

    /**
     * Behaves differently to other models
     * Properties are class properties rather
     * than contained within a 'data' property
     * 
     * @param $data
     */
    public function init($data)
    {
        $this->total_entries = $data['total_entries'] ?? 0;
        $this->total_users = $data['total_users'] ?? 0;
        $this->form_counts = $data['form_counts'] ?? [];
        $this->branch_counts = $data['branch_counts'] ?? [];
        $this->structure_last_updated = $data['structure_last_updated'] ?? '';
    }

    /**
     * @param array $data
     */
    public function updateProjectDetails(array $data)
    {
        //
    }

    /**
     * Get project stats data from public class properties
     *
     * @return array
     */
    public function getData() : array
    {
        $out = [];
        // Get this class properties
        $reflection = new ReflectionClass($this);

        // Loop round properties and set those which also exist in the $projectDetails array
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $out[$property->name] = $this->{$property->name};
        }

        return $out;
    }

    /**
     * Get project stats data from public class properties (JSON encoded)
     *
     * @return array
     */
    public function getJsonData() : array
    {
        $out = [];
        // Get this class properties
        $reflection = new ReflectionClass($this);

        // Loop round properties and set those which also exist in the $projectDetails array
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $out[$property->name] = is_array($this->{$property->name}) ? json_encode($this->{$property->name}) : $this->{$property->name};
        }

        return $out;
    }

    /**
     * @return mixed
     */
    public function getTotalEntries()
    {
        return $this->total_entries;
    }

    /**
     * @return mixed
     */
    public function getTotalUsers()
    {
        return $this->total_users;
    }

    /**
     * @return mixed
     */
    public function getFormCounts()
    {
        return $this->form_counts;
    }

    /**
     * @return mixed
     */
    public function getBranchCounts()
    {
        return $this->branch_counts;
    }

    /**
     * Get the last updated date of the project structures
     *
     * @return null | int
     */
    public function getProjectStructureLastUpdated()
    {
        return $this->structure_last_updated;
    }

    /**
     * Reset the stats values to defaults
     */
    public function resetStats()
    {

        $this->total_entries = 0;
        $this->total_users = 0;
        foreach ($this->form_counts as $formRef => $form) {
            $this->form_counts[$formRef] = [
                'count' => 0,
                'last_entry_created' => '',
                'first_entry_created' => ''
            ];
        }
        foreach ($this->branch_counts as $branchRef => $branch) {
            $this->branch_counts[$branchRef] = [
                'count' => 0,
                'last_entry_created' => '',
                'first_entry_created' => ''
            ];
        }

    }

}