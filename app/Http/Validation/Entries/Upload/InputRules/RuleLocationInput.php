<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;

class RuleLocationInput extends RuleInputBase
{
    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param ProjectDTO $project
     */
    public function setRules($inputDetails, $answer, ProjectDTO $project): void
    {
        // Set rules based on the input details
        // Source will be the input ref

        $this->rules[$inputDetails['ref']] = ['array'];

        // No need to set any rules in the parent class
    }

    /**
     * @param $inputDetails
     * @param $answer
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @return array|string|null
     */
    public function additionalChecks($inputDetails, $answer, ProjectDTO $project, EntryStructureDTO $entryStructure): array|string|null
    {
        if (count($answer) > 0) {
            // Check we have no extra keys
            if (count(array_merge(
                array_diff(array_keys($answer), array_keys(config('epicollect.strings.entry_location_keys'))),
                array_diff(array_keys(config('epicollect.strings.entry_location_keys')), array_keys($answer))
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

            // Limit lat long to max 6 decimal places
            $answer['longitude'] = round($answer['longitude'], 6);
            $answer['latitude'] = round($answer['latitude'], 6);
            //Round accuracy to integer
            $answer['accuracy'] = round($answer['accuracy']);

            //add the geojson entry to the current entryStructure
            //imp: it is done here because since have direct access to the current input ref
            $entryStructure->addGeoJsonObject($inputDetails, $answer);
        }
        return $answer;
    }

    private function isValidLatitude($latitude): bool
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

    private function isValidLongitude($longitude): bool
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

    private function isValidAccuracy($accuracy): bool
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
