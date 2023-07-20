<?php

namespace ec5\Models\ProjectData;

use Config;
use ec5\Models\Projects\Project;
use ec5\Models\Users\User;
use ec5\Libraries\Utilities\GpointConverter;
use ec5\Libraries\Utilities\DateFormatConverter;

class DataMappingHelper
{

    protected $project;
    protected $format;
    protected $entryType;
    protected $inputsInOrder;
    protected $map;
    protected $mappingReservedKeys;
    protected $outputUuid = true;
    protected $parentFormMap = [];
    protected $isTopHierarchyForm = false;
    protected $phpDateFormatSwap = false;

    public function __construct()
    {
        $this->mappingReservedKeys = Config::get('ec5Strings.mapping_reserved_keys');
        $this->phpDateFormatSwap = Config::get('ec5Enums.datetime_format_php');

        // todo: this file is a candidate for refactor using a common interface and two concrete
        // todo: implementations for writing to file - csvFileWriter and jsonFileWriter
    }

    /**
     * @param Project $project
     * @param $format
     * @param $entryType
     * @param $formRef
     * @param $branchRef
     * @param $mapIndex
     */
    public function initialiseMapping(Project $project, $format, $entryType, $formRef, $branchRef, $mapIndex)
    {

        $this->project = $project;

        $projectExtra = $this->project->getProjectExtra();
        // Are we on the first form?
        $this->isTopHierarchyForm = $projectExtra->getFormRefs()[0] === $formRef;

        $this->format = $format;
        $this->entryType = $entryType;

        // Set the mapping
        $this->setMappingInfo($formRef, $branchRef, $mapIndex);
    }

    /**
     * csv header structure !
     * split location make sure its also in function swapOutEntryCsv(below) as LAT and then LONG
     *
     * @return array on csv col headings
     **/
    public function headerRowCsv()
    {
        $entryOut = $this->getRelatedHeaderCsv();
        $entryOut[] = 'created_at';
        $entryOut[] = 'uploaded_at';

        // Add email to private project downloads
        if ($this->project->isPrivate()) {
            $entryOut[] = 'created_by';
        }

        $entryOut[] = 'title';

        foreach ($this->inputsInOrder as $key => $value) {

            $inputRef = $value['ref'];

            $outputKeys = $this->getOutputKeys($inputRef);

            if ($outputKeys['show'] && $outputKeys['mapKey']) {

                switch ($value['type']) {

                    case 'location':
                        $entryOut[] = 'lat_' . $outputKeys['mapKey'];
                        $entryOut[] = 'long_' . $outputKeys['mapKey'];
                        $entryOut[] = 'accuracy_' . $outputKeys['mapKey'];
                        $entryOut[] = 'UTM_Northing_' . $outputKeys['mapKey'];
                        $entryOut[] = 'UTM_Easting_' . $outputKeys['mapKey'];

                        $entryOut[] = 'UTM_Zone_' . $outputKeys['mapKey'];
                        break;

                    default:
                        $entryOut[] = $outputKeys['mapKey'];
                        break;
                }
            }
        }
        return $entryOut;
    }

    /**
     * csv extra header structure !
     * @return array on csv col headings
     **/
    public function getRelatedHeaderCsv()
    {
        $out = [];

        if ($this->outputUuid) {

            if ($this->entryType == Config::get('ec5Strings.branch')) {

                $out[] = $this->mappingReservedKeys['branch_owner_uuid'];
                $out[] = $this->mappingReservedKeys['branch_uuid'];
            } else {
                //guess both or one ???
                $out[] = $this->mappingReservedKeys['entry_uuid'];
                if (!$this->isTopHierarchyForm) {
                    $out[] = $this->mappingReservedKeys['parent_uuid'];
                }
            }
        }

        return $out;
    }

    /**
     * csv rows !
     * split location make sure its also in function headerRowCsv(upbove) as LAT and then LONG
     *
     * @param $jsonEntryString
     * @param string $branchCountsString
     * @param $userId
     * @return array
     */
    public function swapOutEntryCsv($jsonEntryString, $branchCountsString = '', $userId, $title, $uploaded_at)
    {
        $jsonEntry = null;
        $entryOut = [];

        try {
            $jsonEntry = json_decode($jsonEntryString);
            $jsonBranchCounts = json_decode($branchCountsString);
        } catch (\Exception $e) {
            return [];
        }

        if (empty($jsonEntry)) {
            return $entryOut;
        }

        $type = $jsonEntry->type;

        $entry = ($jsonEntry->$type) ? ($jsonEntry->$type) : null;

        // If we are outputting the uuid
        if ($this->outputUuid) {
            switch ($this->entryType) {
                case 'branch':
                    $entryOut = $this->getRelatedValuesCSVBranch(
                        $jsonEntry->branch_entry->entry_uuid,
                        $jsonEntry->relationships
                    );
                    break;
                case 'form':
                    $entryOut = $this->getRelatedValuesCSVForm(
                        $jsonEntry->entry->entry_uuid,
                        $jsonEntry->relationships
                    );
                    break;
            }
        }

        $entryOut[] = $entry->created_at;
        $entryOut[] = DateFormatConverter::mySQLToISO($uploaded_at); //uploaded_at

        // Add email to private project downloads
        if ($this->project->isPrivate()) {
            $entryOut['created_by'] = 'n/a';
            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    $entryOut['created_by'] = $user->email;
                }
            }
        }
        //add title (fingers crossed)
        $entryOut[] = $title; //title

        if ($entry == null) {
            return $entryOut;
        }

        // Value here == to ec5 input structure, ie type, min, max etc...
        foreach ($this->inputsInOrder as $key => $input) {

            $inputRef = $input['ref'];
            $outputKeys = $this->getOutputKeys($inputRef);

            if ($outputKeys['show'] && $outputKeys['mapKey']) {

                $answer = $entry->answers->$inputRef->answer ?? '';

                switch ($input['type']) {

                    case 'location':
                        $locAnswer = $this->parseInputAnswer('csv-location', $answer, $input);
                        $entryOut[] = $locAnswer[0] ?? '';
                        $entryOut[] = $locAnswer[1] ?? '';
                        $entryOut[] = $locAnswer[2] ?? '';
                        $entryOut[] = $locAnswer[3] ?? '';
                        $entryOut[] = $locAnswer[4] ?? '';
                        $entryOut[] = $locAnswer[5] ?? '';
                        break;
                    case 'branch':
                        $entryOut[] = $this->parseInputAnswer(
                            $input['type'],
                            $jsonBranchCounts->$inputRef ?? 0,
                            $input
                        );
                        break;
                    default:
                        $entryOut[] = $this->parseInputAnswer($input['type'], $answer, $input);
                        break;
                }
            }
        }
        return $entryOut;
    }

    /**
     * DO NOT DELETE OR RENAME
     * name of function import uses ->$entryType to be called
     * csv extra  ! only run if $this->outputUuid == true
     *
     * @param $uuid
     * @param $a
     * @return array
     */
    private function getRelatedValuesCSVForm($uuid, $a)
    {
        $out = [];

        //!!! remember order matters so don't change, otherwise need to change other places where header is ...

        $out[] = $uuid;

        if ($this->isTopHierarchyForm) {
            return $out;
        }

        $out[] = $a->parent->data->parent_entry_uuid ?? '';

        return $out;
    }

    /**
     * DO NOT DELETE OR RENAME
     * name of function import uses ->$entryType to be called
     * csv extra  ! only run if $this->outputUuid == true
     *
     * @param $uuid
     * @param $relationships
     * @return array - on csv col headings
     */
    private function getRelatedValuesCSVBranch($uuid, $relationships)
    {
        $out = [];
        //!!! remember order matters so don't change, otherwise need to change other places where header is ...

        $out[] = $relationships->branch->data->owner_entry_uuid ?? '';
        $out[] = $uuid;

        //      //  $inputRef = $relationships->branch->data->owner_input_ref ?? '';
        //        $out[] = (!empty($inputRef) && isset($this->parentFormMap[$inputRef]['map_to'])) ? $this->parentFormMap[$inputRef]['map_to'] : '';

        return $out;
    }

    /**
     * @param $jsonEntryString
     * @param string $branchCountsString
     * @param $userId
     * @param $title
     * @param $uploaded_at
     * @return array|string
     */
    public function swapOutEntryJson($jsonEntryString, $branchCountsString = '', $userId, $title, $uploaded_at)
    {
        $jsonEntry = null;
        $entryOut = [];

        try {
            $jsonEntry = json_decode($jsonEntryString);
            $jsonBranchCounts = json_decode($branchCountsString);
        } catch (\Exception $e) {
            return '';
        }

        if (empty($jsonEntry)) {
            return $entryOut;
        }

        $type = $jsonEntry->type;
        $entry = $jsonEntry->{$type} ?? null;


        // If we are outputting the uuid
        if ($this->outputUuid) {
            switch ($this->entryType) {
                case 'branch':
                    $entryOut = $this->getRelatedValuesJSONBranch($entry->entry_uuid, $jsonEntry->relationships);
                    break;
                case 'form':
                    $entryOut = $this->getRelatedValuesJSONForm($entry->entry_uuid, $jsonEntry->relationships);
                    break;
            }
        }

        $entryOut['created_at'] = $entry->created_at;
        $entryOut['uploaded_at'] = DateFormatConverter::mySQLToISO($uploaded_at);

        // Add email to private project downloads
        if ($this->project->isPrivate()) {
            $entryOut['created_by'] = 'n/a';
            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    $entryOut['created_by'] = $user->email;
                }
            }
        }

        if ($entry == null) {
            return $entryOut;
        }

        //add title (finger crossed)
        $entryOut['title'] = $title;

        foreach ($this->inputsInOrder as $key => $input) {

            $inputRef = $input['ref'];
            $outputKeys = $this->getOutputKeys($inputRef);

            if ($outputKeys['show'] && $outputKeys['mapKey']) {

                $answer = $entry->answers->$inputRef->answer ?? '';

                switch ($input['type']) {

                    case 'branch':
                        $entryOut[$outputKeys['mapKey']] = $this->parseInputAnswer(
                            $input['type'],
                            $jsonBranchCounts->$inputRef ?? 0,
                            $input
                        );
                        break;
                    case 'location':
                        $entryOut[$outputKeys['mapKey']] = $this->parseInputAnswer('json-location', $answer, $input);
                        break;
                    case 'checkbox':
                        $entryOut[$outputKeys['mapKey']] = $this->parseInputAnswer('json-checkbox', $answer, $input);
                        break;

                    case 'searchsingle':
                        $entryOut[$outputKeys['mapKey']] = $this->parseInputAnswer('json-searchsingle', $answer, $input);
                        break;
                    case 'searchmultiple':
                        $entryOut[$outputKeys['mapKey']] = $this->parseInputAnswer('json-searchmultiple', $answer, $input);
                        break;
                    default:
                        $entryOut[$outputKeys['mapKey']] = $this->parseInputAnswer($input['type'], $answer, $input);
                        break;
                }
            }
        }

        // JSON_UNESCAPED_SLASHES will not escape forward slashes, in dates etc
        return json_encode($entryOut, JSON_UNESCAPED_SLASHES);
    }

    /**
     ** DO NOT DELETE OR RENAME
     * name of function import uses ->$entryType to be called
     * csv extra  ! only run if $this->outputUuid == true
     *
     * @param $uuid
     * @param $relationships
     * @return array - on csv col headings
     */
    private function getRelatedValuesJSONForm($uuid, $relationships)
    {

        $out = [];

        //!!! remember order matters so don't change, otherwise need to change other places where header is ...

        $out[$this->mappingReservedKeys['entry_uuid']] = $uuid;

        if ($this->isTopHierarchyForm) {
            return $out;
        }

        $out[$this->mappingReservedKeys['parent_uuid']] = $relationships->parent->data->parent_entry_uuid ?? '';

        return $out;
    }

    /**
     * DO NOT DELETE OR RENAME
     * name of function import uses ->$entryType to be called
     * csv extra  ! only run if $this->outputUuid == true
     * @param $uuid
     * @param $relationships
     * @return array - on csv col headings
     */
    private function getRelatedValuesJSONBranch($uuid, $relationships)
    {

        $out = [];
        //!!! remember order matters so don't change, otherwise need to change other places where header is ...

        $out[$this->mappingReservedKeys['branch_owner_uuid']] = $relationships->branch->data->owner_entry_uuid ?? '';
        $out[$this->mappingReservedKeys['branch_uuid']] = $uuid;

        //$inputRef = $relationships->branch->data->owner_input_ref ?? '';

        //        $out[$this->mappingReservedKeys['branch_ref']] =
        //            (!empty($inputRef) && isset($this->parentFormMap[$inputRef]['map_to'])) ? $this->parentFormMap[$inputRef]['map_to'] : '';
        return $out;
    }

    /**
     * Get keys for output show and key for mapping
     *
     * @param $inputRef
     * @return array
     */
    private function getOutputKeys($inputRef)
    {
        return [
            'show' => (isset($this->map[$inputRef]['hide']) && $this->map[$inputRef]['hide']) ? false : true,
            'mapKey' => (empty($this->map[$inputRef]['map_to'])) ? false : $this->map[$inputRef]['map_to']
        ];
    }

    /**
     * Get custom or default mapping and inputs in order for swap
     * Groups are added to the top level for ease of access
     *
     * @param string $formRef
     * @param string $branchRef
     * @param int - or null $mapIndex
     */
    private function setMappingInfo($formRef, $branchRef, $mapIndex)
    {
        $projectMapping = $this->project->getProjectMapping();
        $projectExtra = $this->project->getProjectExtra();
        $outputMap = $projectMapping->getMap($mapIndex, $formRef);

        if (empty($outputMap)) {
            $outputMap = $projectMapping->getMap(0, $formRef);
        }

        switch ($this->entryType) {
            case 'form':
                // Get the form inputs in order
                $this->inputsInOrder = $projectExtra->getFormInputData($formRef);
                break;
            case 'branch':
                // Set the parent form map
                $this->parentFormMap = $outputMap;
                // Get the branch map
                $outputMap = $outputMap[$branchRef]['branch'];
                // Get the branch inputs in order
                $this->inputsInOrder = $projectExtra->getBranchInputData($formRef, $branchRef);
                break;
        }

        $map = $outputMap;
        // Merge nested group maps with top level map array
        foreach ($outputMap as $inputRef => $input) {
            if (count($input['group']) > 0) {
                $map = array_merge($map, $input['group']);
            }
        }

        $this->map = $map;
    }

    /**
     * Parse the answer
     *
     * @param $type
     * @param $answer
     * @param $ec5Input
     * @return array|string
     */
    private function parseInputAnswer($type, $answer, $ec5Input)
    {

        // Default empty string
        $parsedAnswer = '';
        $converter = new GpointConverter();

        switch ($type) {

                //todo input type should be constants....
            case 'radio':
            case 'dropdown':
                $parsedAnswer = $this->getPossibleAnswerMapping($ec5Input, $answer);
                break;

            case 'searchsingle':
            case 'searchmultiple':
            case 'checkbox':

                $temp = [];

                if (is_array($answer)) {
                    foreach ($answer as $key => $value) {
                        $parsedAnswer = $this->getPossibleAnswerMapping($ec5Input, $value);

                        //if $parsedAnswer contains commas, wrap in quotes as per csv specs. 
                        if (strpos($parsedAnswer, ',') !== false) {
                            $parsedAnswer = '"' . $parsedAnswer . '"';
                        }

                        $temp[] = $parsedAnswer;
                    }
                }

                $parsedAnswer = count($temp) === 0 ? '' : implode(', ', $temp);
                break;
            case 'json-searchsingle':
            case 'json-searchmultiple':
            case 'json-checkbox':
                $temp = [];

                if (is_array($answer)) {
                    foreach ($answer as $key => $value) {
                        $parsedAnswer = $this->getPossibleAnswerMapping($ec5Input, $value);
                        $temp[] = $parsedAnswer;
                    }
                }

                $parsedAnswer = count($temp) == 0 ? [] : $temp;
                break;
            case 'location':
                $parsedAnswer = $answer->latitude ?? '';
                $parsedAnswer .= ', ';
                $parsedAnswer .= $answer->longitude ?? '';
                break;

            case 'csv-location':
                try {
                    if ($answer->latitude && $answer->longitude) {
                        $converter->setLongLat($answer->longitude, $answer->latitude);
                        $converter->convertLLtoTM(null);

                        $parsedAnswer = [
                            $answer->latitude ?? '',
                            $answer->longitude ?? '',
                            $answer->accuracy ?? '',
                            (int)$converter->N(),
                            (int)$converter->E(),
                            $converter->Z()
                        ];
                    } else {
                        $parsedAnswer = [
                            $answer->latitude ?? '',
                            $answer->longitude ?? '',
                            $answer->accuracy ?? '',
                            '',
                            '',
                            ''
                        ];
                    }
                } catch (\Exception $e) {
                    //we get here when there is not an answer?
                    $parsedAnswer = [
                        '',
                        '',
                        '',
                        '',
                        '',
                        ''
                    ];
                }
                break;
            case 'json-location':
                try {
                    if ($answer->latitude && $answer->longitude) {
                        $converter->setLongLat($answer->longitude, $answer->latitude);
                        $converter->convertLLtoTM(null);

                        $parsedAnswer = [
                            'latitude' => $answer->latitude ?? '',
                            'longitude' => $answer->longitude ?? '',
                            'accuracy' => $answer->accuracy ?? '',
                            'UTM_Northing' => (int)$converter->N(),
                            'UTM_Easting' => (int)$converter->E(),
                            'UTM_Zone' => $converter->Z()
                        ];
                    } else {
                        $parsedAnswer = [
                            'latitude' => $answer->latitude ?? '',
                            'longitude' => $answer->longitude ?? '',
                            'accuracy' => $answer->accuracy ?? '',
                            'UTM_Northing' => '',
                            'UTM_Easting' => '',
                            'UTM_Zone' => ''
                        ];
                    }
                } catch (\Exception $e) {
                    $parsedAnswer = [
                        'latitude' => '',
                        'longitude' => '',
                        'accuracy' => '',
                        'UTM_Northing' => '',
                        'UTM_Easting' => '',
                        'UTM_Zone' => ''
                    ];
                }
                break;
            case 'date':
            case 'time':
                // If we have a non empty date/time, parse
                if (!empty($answer)) {
                    $dFormat = $this->phpDateFormatSwap[$ec5Input['datetime_format']];
                    $parsedAnswer = (empty($dFormat)) ? '' : date($dFormat, strtotime($answer));
                }
                break;
            case 'photo':
                $parsedAnswer = $this->getMediaUrl($type, 'entry_original', $answer);
                break;
            case 'video':
                $parsedAnswer = $this->getMediaUrl($type, 'video', $answer);
                break;
            case 'audio':
                $parsedAnswer = $this->getMediaUrl($type, 'audio', $answer);
                break;
            case 'integer':
                //force cast to int
                $parsedAnswer = ($answer === '') ? '' : (int) $answer;
                break;
            case 'decimal':
                //force cast to float (iOS decimals comes as strings)
                $parsedAnswer = ($answer === '') ? '' : floatval($answer);
                break;
            default:
                $parsedAnswer = $answer;
                break;
        }

        return $parsedAnswer;
    }

    /**
     * @param $type
     * @param $format
     * @param $fileName
     * @return string
     */
    private function getMediaUrl($type, $format, $fileName): string
    {
        // Public - provide url
        if (!empty($fileName) && $this->project->isPublic()) {
            return url('api/media') . '/' . $this->project->slug . '?type=' . $type . '&format=' . $format . '&name=' . $fileName;
        }

        // Private - filename
        return $fileName;
    }

    private function getPossibleAnswerMapping($input, $answerRef)
    {
        return $this->map[$input['ref']]['possible_answers'][$answerRef]['map_to'] ?? '';
    }
}
