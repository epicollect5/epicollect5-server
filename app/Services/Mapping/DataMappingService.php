<?php

namespace ec5\Services\Mapping;

use Carbon\Carbon;
use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\GPointConverter;
use ec5\Models\User\User;
use Log;
use Throwable;

class DataMappingService
{
    protected ProjectDTO $project;
    protected array $forms;
    protected string $format;
    protected string $type;
    protected array $inputsFlattened;
    protected array $map;
    protected array $mappingEC5Keys;
    protected array $parentFormMap = [];
    protected bool $isTopHierarchyForm = false;
    protected array $datetimeFormatsPHP;

    public function __construct()
    {
        $this->mappingEC5Keys = config('epicollect.strings.mapping_ec5_keys');
        $this->datetimeFormatsPHP = config('epicollect.mappings.datetime_formats_php');
        // todo: this file is a candidate for refactor using a common interface and two concrete
        // todo: implementations for writing to file - csvFileWriter and jsonFileWriter
    }

    public function init(ProjectDTO $project, $format, $type, $formRef, $branchRef, $mapIndex): void
    {
        $this->project = $project;
        $this->forms = $project->getProjectDefinition()->getData()['project']['forms'];
        //Is this the top hierarchy form?
        $this->isTopHierarchyForm = $this->forms[0]['ref'] === $formRef;
        $this->format = $format;
        $this->type = $type;
        // Set the mapping
        $this->setupMapping($formRef, $branchRef, $mapIndex);
    }

    public function getHeaderRowCSV(): array
    {
        $output = $this->getUUIDHeadersCSV();
        $output[] = 'created_at';
        $output[] = 'uploaded_at';

        // Add creates_by metadata (will be user email) to private projects downloads
        if ($this->project->isPrivate()) {
            $output[] = 'created_by';
        }

        $output[] = 'title';

        foreach ($this->inputsFlattened as $input) {

            $inputRef = $input['ref'];
            $inputMapping = $this->getInputMapping($inputRef);

            if (!$inputMapping['hide'] && $inputMapping['mapTo']) {

                switch ($input['type']) {
                    case 'location':
                        $output[] = 'lat_' . $inputMapping['mapTo'];
                        $output[] = 'long_' . $inputMapping['mapTo'];
                        $output[] = 'accuracy_' . $inputMapping['mapTo'];
                        $output[] = 'UTM_Northing_' . $inputMapping['mapTo'];
                        $output[] = 'UTM_Easting_' . $inputMapping['mapTo'];
                        $output[] = 'UTM_Zone_' . $inputMapping['mapTo'];
                        break;

                    default:
                        $output[] = $inputMapping['mapTo'];
                        break;
                }
            }
        }
        return $output;
    }

    public function getUUIDHeadersCSV(): array
    {
        $out = [];
        if ($this->type == config('epicollect.strings.branch')) {
            $out[] = $this->mappingEC5Keys['ec5_branch_owner_uuid'];
            $out[] = $this->mappingEC5Keys['ec5_branch_uuid'];
        } else {
            //guess both or one ???
            $out[] = $this->mappingEC5Keys['ec5_uuid'];
            if (!$this->isTopHierarchyForm) {
                $out[] = $this->mappingEC5Keys['ec5_parent_uuid'];
            }
        }
        return $out;
    }

    public function getMappedEntryCSV($JSONEntryString, $userId, $title, $uploaded_at, $branchCountsString = ''): array
    {
        $output = [];
        try {
            $JSONEntry = json_decode($JSONEntryString, true);
            $JSONBranchCounts = json_decode($branchCountsString, true);
        } catch (Throwable) {
            return $output;
        }

        if (empty($JSONEntry)) {
            return $output;
        }

        $entry = [];
        switch ($this->type) {
            case config('epicollect.strings.form'):
                $entry = $JSONEntry['entry'];
                $output = $this->getUUIDHeadersForm(
                    $JSONEntry['entry']['entry_uuid'],
                    $JSONEntry['relationships']
                );
                break;
            case config('epicollect.strings.branch'):
                $entry = $JSONEntry['branch_entry'];
                $output = $this->getUUIDHeadersBranch(
                    $JSONEntry['branch_entry']['entry_uuid'],
                    $JSONEntry['relationships']
                );
                break;
        }

        $output[] = $entry['created_at'];
        $output[] = $this->convertMYSQLDateToISO($uploaded_at);

        // Add email to private project downloads
        if ($this->project->isPrivate()) {
            $output['created_by'] = 'n/a';
            if ($userId) {
                $user = User::where('id', $userId)
                    ->where('state', '<>', 'archived')
                    ->first();
                if ($user) {
                    $output['created_by'] = $user->email;
                }
            }
        }
        //add title (fingers crossed)
        $output[] = $title; //title

        foreach ($this->inputsFlattened as $input) {
            $inputRef = $input['ref'];
            $inputMapping = $this->getInputMapping($inputRef);

            if (!$inputMapping['hide'] && $inputMapping['mapTo']) {

                $answer = $entry['answers'][$inputRef]['answer'] ?? '';

                switch ($input['type']) {
                    case 'location':

                        $locationAnswer = $this->parseAnswer('csv-location', $answer, $input);
                        $output[] = $locationAnswer[0] ?? '';
                        $output[] = $locationAnswer[1] ?? '';
                        $output[] = $locationAnswer[2] ?? '';
                        $output[] = $locationAnswer[3] ?? '';
                        $output[] = $locationAnswer[4] ?? '';
                        $output[] = $locationAnswer[5] ?? '';
                        break;
                    case 'branch':
                        $output[] = $this->parseAnswer(
                            $input['type'],
                            $JSONBranchCounts[$inputRef] ?? 0,
                            $input
                        );
                        break;
                    default:
                        $output[] = $this->parseAnswer($input['type'], $answer, $input);
                        break;
                }
            }
        }

        return $output;
    }

    private function getUUIDHeadersForm($uuid, $relationships): array
    {
        //imp: order matters so don't change, otherwise need to change other places where header is ...
        $out = [];
        $out[] = $uuid;
        if ($this->isTopHierarchyForm) {
            return $out;
        }
        //child entry needs the parent entry uuid
        $out[] = $relationships['parent']['data']['parent_entry_uuid'] ?? '';
        return $out;
    }

    private function getUUIDHeadersBranch($uuid, $relationships): array
    {
        $out = [];
        //imp: order matters so don't change, otherwise need to change other places where header is ...
        $out[] = $relationships['branch']['data']['owner_entry_uuid'] ?? '';
        $out[] = $uuid;
        return $out;
    }

    public function getMappedEntryJSON($JSONEntryString, $userId, $title, $uploaded_at, $branchCountsString = ''): false|array|string
    {
        $output = [];
        try {
            $JSONEntry = json_decode($JSONEntryString, true);
            $JSONBranchCounts = json_decode($branchCountsString, true);
        } catch (Throwable) {
            return '';
        }

        if (empty($JSONEntry)) {
            return $output;
        }

        $type = $JSONEntry['type'];
        $entry = $JSONEntry[$type] ?? null;
        switch ($this->type) {
            case 'form':
                $output = $this->getUUIDHeadersJSONForm($entry['entry_uuid'], $JSONEntry['relationships']);
                break;
            case 'branch':
                $output = $this->getUUIDHeadersJSONBranch($entry['entry_uuid'], $JSONEntry['relationships']);
                break;
        }
        //add timestamps (ISO)
        $output['created_at'] = $entry['created_at'];
        $output['uploaded_at'] = $this->convertMYSQLDateToISO($uploaded_at);
        // Add email to private project downloads
        if ($this->project->isPrivate()) {
            $output['created_by'] = 'n/a';
            if ($userId) {
                $user = User::where('id', $userId)
                    ->where('state', '<>', 'archived')
                    ->first();
                if ($user) {
                    $output['created_by'] = $user->email;
                }
            }
        }

        if ($entry == null) {
            return $output;
        }

        //add title (finger crossed)
        $output['title'] = $title;

        foreach ($this->inputsFlattened as $input) {

            $inputRef = $input['ref'];
            $inputMapping = $this->getInputMapping($inputRef);

            if (!$inputMapping['hide'] && $inputMapping['mapTo']) {

                $answer = $entry['answers'][$inputRef]['answer'] ?? '';

                switch ($input['type']) {
                    case 'branch':
                        $output[$inputMapping['mapTo']] = $this->parseAnswer(
                            $input['type'],
                            $JSONBranchCounts[$inputRef] ?? 0,
                            $input
                        );
                        break;
                    case 'location':
                        $output[$inputMapping['mapTo']] = $this->parseAnswer('json-location', $answer, $input);
                        break;
                    case 'checkbox':
                        $output[$inputMapping['mapTo']] = $this->parseAnswer('json-checkbox', $answer, $input);
                        break;
                    case 'searchsingle':
                        $output[$inputMapping['mapTo']] = $this->parseAnswer('json-searchsingle', $answer, $input);
                        break;
                    case 'searchmultiple':
                        $output[$inputMapping['mapTo']] = $this->parseAnswer('json-searchmultiple', $answer, $input);
                        break;
                    default:
                        $output[$inputMapping['mapTo']] = $this->parseAnswer($input['type'], $answer, $input);
                        break;
                }
            }
        }

        // JSON_UNESCAPED_SLASHES will not escape forward slashes, in dates etc
        return json_encode($output, JSON_UNESCAPED_SLASHES);
    }

    private function getUUIDHeadersJSONForm($uuid, $relationships): array
    {
        $out = [];

        //!!! remember order matters so don't change, otherwise need to change other places where header is ...
        $out[$this->mappingEC5Keys['ec5_uuid']] = $uuid;

        if ($this->isTopHierarchyForm) {
            return $out;
        }


        $out[$this->mappingEC5Keys['ec5_parent_uuid']] = $relationships['parent']['data']['parent_entry_uuid'] ?? '';

        return $out;
    }

    private function getUUIDHeadersJSONBranch($uuid, $relationships): array
    {
        $output = [];
        //!!! remember order matters so don't change, otherwise need to change other places where header is ...

        $output[$this->mappingEC5Keys['ec5_branch_owner_uuid']] = $relationships['branch']['data']['owner_entry_uuid'] ?? '';
        $output[$this->mappingEC5Keys['ec5_branch_uuid']] = $uuid;
        return $output;
    }

    /**
     * Get keys for output show and key for mapping
     */
    private function getInputMapping($inputRef): array
    {
        return [
            'hide' => (isset($this->map[$inputRef]['hide']) && $this->map[$inputRef]['hide']),
            'mapTo' => (empty($this->map[$inputRef]['map_to'])) ? false : $this->map[$inputRef]['map_to']
        ];
    }

    /**
     * Get custom or default mapping and inputs in order for swap
     * Groups are added to the top level for ease of access
     */
    private function setupMapping($formRef, $branchRef, $mapIndex): void
    {
        $projectMapping = $this->project->getProjectMapping();
        $selectedMapping = $projectMapping->getMap($mapIndex, $formRef);

        if (empty($selectedMapping)) {
            $selectedMapping = $projectMapping->getMap(0, $formRef);
        }

        switch ($this->type) {
            case config('epicollect.strings.form'):
                // Get the form inputs as a flat array
                $this->inputsFlattened = $this->getInputsFlattened($this->forms, $formRef);
                break;
            case config('epicollect.strings.branch'):
                // Set the parent form map
                $this->parentFormMap = $selectedMapping;
                // Get the branch map
                $selectedMapping = $selectedMapping[$branchRef]['branch'];
                // Get the branch inputs in order
                $this->inputsFlattened = $this->getBranchInputsFlattened($this->forms, $formRef, $branchRef);
                break;
        }

        $mapping = $selectedMapping;
        // Merge nested group maps with the top level map array
        foreach ($selectedMapping as $input) {
            if (count($input['group']) > 0) {
                $mapping = array_merge($mapping, $input['group']);
            }
        }

        $this->map = $mapping;
    }

    private function parseAnswer($type, $answer, $input)
    {
        $parsedAnswer = '';
        $converter = new GPointConverter();

        switch ($type) {
            //todo input type should be constants....
            case 'radio':
            case 'dropdown':
                $parsedAnswer = $this->getPossibleAnswerMapTo($input, $answer);
                break;

            case 'searchsingle':
            case 'searchmultiple':
            case 'checkbox':
                $temp = [];
                if (is_array($answer)) {
                    foreach ($answer as $value) {
                        $parsedAnswer = $this->getPossibleAnswerMapTo($input, $value);
                        //if $parsedAnswer contains commas, wrap in quotes as per csv specs.
                        if (str_contains($parsedAnswer, ',')) {
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
                    foreach ($answer as $value) {
                        $parsedAnswer = $this->getPossibleAnswerMapTo($input, $value);
                        $temp[] = $parsedAnswer;
                    }
                }

                $parsedAnswer = count($temp) == 0 ? [] : $temp;
                break;
            case 'location':
                $parsedAnswer = $answer['latitude'] ?? '';
                $parsedAnswer .= ', ';
                $parsedAnswer .= $answer['longitude'] ?? '';
                break;

            case 'csv-location':
                try {
                    if ($answer['latitude'] && $answer['longitude']) {
                        $converter->setLongLat($answer['longitude'], $answer['latitude']);
                        $converter->convertLLtoTM(null);

                        $parsedAnswer = [
                            $answer['latitude'],
                            $answer['longitude'],
                            $answer['accuracy'],
                            (int)$converter->N(),
                            (int)$converter->E(),
                            $converter->Z()
                        ];
                    } else {
                        $parsedAnswer = [
                            $answer['latitude'] ?? '',
                            $answer['longitude'] ?? '',
                            $answer['accuracy'] ?? '',
                            '',
                            '',
                            ''
                        ];
                    }
                } catch (Throwable $e) {
                    Log::debug(__METHOD__ . ' failed | csv-location', ['exception' => $e->getMessage()]);
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
                    if ($answer['latitude'] && $answer['longitude']) {
                        $converter->setLongLat($answer['longitude'], $answer['latitude']);
                        $converter->convertLLtoTM(null);

                        $parsedAnswer = [
                            'latitude' => $answer['latitude'],
                            'longitude' => $answer['longitude'],
                            'accuracy' => $answer['accuracy'],
                            'UTM_Northing' => (int)$converter->N(),
                            'UTM_Easting' => (int)$converter->E(),
                            'UTM_Zone' => $converter->Z()
                        ];
                    } else {
                        $parsedAnswer = [
                            'latitude' => $answer['latitude'] ?? '',
                            'longitude' => $answer['longitude'] ?? '',
                            'accuracy' => $answer['accuracy'] ?? '',
                            'UTM_Northing' => '',
                            'UTM_Easting' => '',
                            'UTM_Zone' => ''
                        ];
                    }
                } catch (Throwable) {
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
                // If we have a non-empty date/time, parse
                if (!empty($answer)) {
                    $datetimeFormat = $this->datetimeFormatsPHP[$input['datetime_format']];
                    $parsedAnswer = (empty($datetimeFormat)) ? '' : date($datetimeFormat, strtotime($answer));
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
                $parsedAnswer = ($answer === '') ? '' : (int)$answer;
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

    private function getMediaUrl($type, $format, $fileName): string
    {
        // Public - provide url
        if (!empty($fileName) && $this->project->isPublic()) {
            return url('api/media') . '/' . $this->project->slug . '?type=' . $type . '&format=' . $format . '&name=' . $fileName;
        }

        // Private - filename
        return $fileName;
    }

    private function getPossibleAnswerMapTo($input, $answerRef)
    {
        return $this->map[$input['ref']]['possible_answers'][$answerRef]['map_to'] ?? '';
    }

    /**
     * @param $forms
     * @param $formRef
     * @return array
     *
     * Used to get a list of top level inputs and groups
     * as a flat list, skipping branch inputs
     * @see DataMappingService::setupMapping()
     */
    public function getInputsFlattened($forms, $formRef): array
    {
        $inputs = [];
        $flattenInputs = [];
        foreach ($forms as $form) {
            if ($form['ref'] === $formRef) {
                $inputs = $form['inputs'];
            }
        }

        //todo: where is the readme skipped?
        //imp: it is skipped when saving the entry payload to the DB
        foreach ($inputs as $input) {
            if ($input['type'] == config('epicollect.strings.inputs_type.group')) {
                foreach ($input['group'] as $groupInput) {
                    $flattenInputs[] = $groupInput;
                }
            } else {
                $flattenInputs[] = $input;
            }
        }
        return $flattenInputs;
    }

    /* This function returns a flat list of inputs and nested group inputs,
    * dropping the group inputs owner
    */
    public function getBranchInputsFlattened($forms, $formRef, $branchInputRef): array
    {
        $branchInputs = [];
        $flattenBranchInputs = [];
        foreach ($forms as $form) {
            if ($form['ref'] === $formRef) {
                $inputs = $form['inputs'];
                foreach ($inputs as $input) {
                    if ($input['ref'] === $branchInputRef) {
                        $branchInputs = $input['branch'];
                    }
                }
            }
        }

        /**
         * imp: the readme is skipped in the ProjectMappingService
         * @see ProjectMappingService::getMappedInputs()
         */
        foreach ($branchInputs as $branchInput) {
            if ($branchInput['type'] == config('epicollect.strings.inputs_type.group')) {
                foreach ($branchInput['group'] as $groupInput) {
                    $flattenBranchInputs[] = $groupInput;
                }
            } else {
                $flattenBranchInputs[] = $branchInput;
            }
        }
        return $flattenBranchInputs;
    }

    //convert a MYSQL date (2024-08-15 17:37:46.000) to Javascript equivalent
    //imp: we are also dropping milliseconds (pre Laravel 7 behaviour)
    private function convertMYSQLDateToISO($mysqlDate): string
    {
        return Carbon::parse($mysqlDate)->format('Y-m-d\TH:i:s.000\Z');
    }
}
