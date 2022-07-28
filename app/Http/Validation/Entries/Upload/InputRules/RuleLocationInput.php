<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\Models\Projects\Project;
use ec5\Models\Entries\EntryStructure;
use Config;
use ec5\Libraries\EC5Logger\EC5Logger;
use Log;

class RuleLocationInput extends RuleInputBase
{

    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param Project $project
     */
    public function setRules($inputDetails, $answer, Project $project)
    {
        // Set rules based on the input details
        // Source will be the input ref

        $this->rules[$inputDetails['ref']] = ['array'];

        // No need to set any rules in the parent class
    }

    /**
     * @param $inputDetails
     * @param $answer
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return mixed
     */
    public function additionalChecks($inputDetails, $answer, Project $project, EntryStructure $entryStructure)
    {
        if (count($answer) > 0) {
            // Check we have no extra keys
            if (count(array_merge(
                    array_diff(array_keys($answer), Config::get('ec5Enums.entry_location_keys')),
                    array_diff(Config::get('ec5Enums.entry_location_keys'), array_keys($answer))
                )) > 0
            ) {
                $this->errors[$inputDetails['ref']] = ['ec5_30'];
                return false;
            }

            //empty location? It means the user did not submit one, just return empty answer
            if ($answer['latitude'] === '' && $answer['longitude'] === '' && ($answer['accuracy'] === '')) {
                return $answer;
            }

            //any null value? This happens when uploading invalid location values via the csv bulk upload
            if ($answer['accuracy'] === null || $answer['longitude'] === null || $answer['latitude'] === null) {
                $this->errors[$inputDetails['ref']] = ['ec5_30'];
                return false;
            }

            //validate lat
            if (!$this->isValidLatitude($answer['latitude'])) {
                $this->errors[$inputDetails['ref']] = ['ec5_30'];
                return false;
            }

            //validate long
            if (!$this->isValidLongitude($answer['longitude'])) {
                $this->errors[$inputDetails['ref']] = ['ec5_30'];
                return false;
            }

            //validate accuracy
            if (!$this->isValidAccuracy($answer['accuracy'])) {
                $this->errors[$inputDetails['ref']] = ['ec5_30'];
                return false;
            }

            // Limit lat long to max 6dp
            $answer['longitude'] = round($answer['longitude'], 6);
            $answer['latitude'] = round($answer['latitude'], 6);
            //Round accuracy to integer
            $answer['accuracy'] = round($answer['accuracy']);

            //add the geojson entry
            $this->createGeoJson($entryStructure, $inputDetails, $answer);
        }
        return $answer;
    }

    /**
     * Add the geo json object to the entry structure
     * @param EntryStructure $entryStructure
     * @param $inputDetails
     * @param $entryLocation
     */
    private function createGeoJson(EntryStructure $entryStructure, $inputDetails, $entryLocation)
    {

        $geoJson = [];
        $geoJson['type'] = 'Feature';
        $geoJson['id'] = $entryStructure->getEntryUuid();
        $geoJson['geometry'] = [
            'type' => 'Point',
            'coordinates' => [
                $entryLocation['longitude'],
                $entryLocation['latitude']
            ]
        ];
        $geoJson['properties'] = [
            'uuid' => $entryStructure->getEntryUuid(),
            'title' => $entryStructure->getTitle(),
            'accuracy' => $entryLocation['accuracy'],
            'created_at' => date('Y-m-d', strtotime($entryStructure->getDateCreated())),

            // Possible answers will be added at the end
            'possible_answers' => [],
        ];

        $entryStructure->addGeoJson($inputDetails['ref'], $geoJson);
    }

    private function isValidLatitude($latitude)
    {
        if ($latitude === '') {
            return false;
        }
        if (!is_numeric($latitude)) {
            return false;
        }
        if ($latitude < -90 || $latitude > 90) {
            return false;
        }

        return true;
    }

    private function isValidLongitude($longitude)
    {
        if ($longitude === '') {
            return false;
        }
        if (!is_numeric($longitude)) {
            return false;
        }
        if ($longitude < -180 || $longitude > 180) {
            return false;
        }

        return true;
    }

    private function isValidAccuracy($accuracy)
    {
        if ($accuracy === '') {
            return false;
        }
        if (!is_numeric($accuracy)) {
            return false;
        }
        if ($accuracy < 0) {
            return false;
        }

        return true;
    }
}
