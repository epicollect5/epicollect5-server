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

    /**
     * @return string
     */
    public function getJsonData()
    {
        return json_encode($this->data);
    }

    /**
     * Helper function
     * Takes any values from $array2 which have the same key as $array1
     * And stores them in the $to array
     * If a key is not present in $array2, but present in $array1, will take the
     * value from $array1
     *
     * Works recursively for multidimensional associative arrays
     *
     * @param array $array1
     * @param array $array2
     * @param array $to
     * @return array
     */
    public function mergeArrays(array $array1, array $array2, array $to) : array
    {

        // Loop $array1
        foreach ($array1 as $key => $value) {

            // If the key exists in $array2
            if (isset($array2[$key])) {
                // If we have an array, call this function again with the array
                if (is_array($value)) {
                    $to[$key] = $this->mergeArrays($value, $array2[$key], []);
                } else {
                    // Otherwise set the value for this key from $array2
                    $to[$key] = $array2[$key];
                }
            } else {
                // Otherwise set the value from $array1
                $to[$key] = $value;
            }
        }

        return $to;
    }

    /**
     * @param array $data
     */
    abstract function updateProjectDetails(array $data);

    /**
     * @param $oldProjectRef
     * @param $newProjectRef
     */
    public function updateRef($oldProjectRef, $newProjectRef)
    {
        // JSON encode the data
        $jsonData = json_encode($this->data);
        // Replace the old ref with the new ref in the data
        $jsonData = str_replace($oldProjectRef, $newProjectRef, $jsonData);
        // Replace 'this' data
        $this->data = json_decode($jsonData, true);
    }
}
