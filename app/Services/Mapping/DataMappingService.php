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
    protected array $usersCache;
    protected GPointConverter $converter;

    /**
     * Initializes the DataMappingService with configuration settings for mapping keys and datetime formats.
     *
     * Loads the mapping EC5 keys and PHP datetime formats from the configuration.
     *
     * @todo Refactor to use a common interface with separate implementations for CSV and JSON file writers.
     */
    public function __construct()
    {
        $this->mappingEC5Keys = config('epicollect.strings.mapping_ec5_keys');
        $this->datetimeFormatsPHP = config('epicollect.mappings.datetime_formats_php');
        // todo: this file is a candidate for refactor using a common interface and two concrete
        // todo: implementations for writing to file - csvFileWriter and jsonFileWriter
    }

    /**
     * Initializes the DataMappingService with project data and mapping configuration.
     *
     * This method assigns the project instance and retrieves the form definitions from the provided
     * project data. It determines if the specified form reference corresponds to the top-level form,
     * resets the user email cache, and configures the input mapping based on the given form and branch
     * references alongside the mapping index.
     *
     * @param ProjectDTO $project The project data transfer object containing definitions and forms.
     * @param string $format The desired output format (e.g., CSV or JSON).
     * @param string $type The mapping type (e.g., form or branch).
     * @param string $formRef The reference identifier for the main form.
     * @param mixed $branchRef The reference identifier for the branch, if applicable. can be null.
     * @param mixed $mapIndex The index to select the specific mapping configuration.
     *
     * @return void
     */
    public function init(ProjectDTO $project, string $format, string $type, string $formRef, mixed $branchRef, mixed $mapIndex): void
    {
        $this->project = $project;
        $this->forms = $project->getProjectDefinition()->getData()['project']['forms'];
        //Is this the top hierarchy form?
        $this->isTopHierarchyForm = $this->forms[0]['ref'] === $formRef;
        $this->format = $format;
        $this->type = $type;
        $this->usersCache = [];
        // Set the mapping
        $this->setupMapping($formRef, $branchRef, $mapIndex);
        $this->converter = new GPointConverter();
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

    /**
     * Retrieves the UUID header keys for CSV output based on the mapping type.
     *
     * For branch mappings, it returns the branch owner and branch UUID keys.
     * For form mappings, it returns the form UUID key and, if the form is not at the top hierarchy,
     * includes the parent UUID key.
     *
     * @return array An array of UUID header keys appropriate for the current mapping type.
     */
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

    /**
     * Maps a JSON entry to a CSV row based on the current mapping configuration.
     *
     * This method decodes the provided JSON string containing project entry data and branch counts. It determines the entry type (form or branch) and constructs a CSV row that includes UUID headers, creation timestamps, and parsed answers for each input field. Additionally, if the project is private (as indicated by the access flag), it adds the creator's email to the output.
     *
     * @param string $JSONEntryString JSON string containing the project entry data (with keys 'entry' or 'branch_entry') and associated relationships.
     * @param mixed $userId Identifier for the user; used to retrieve the creator's email when required.
     * @param string $title Title or label for the CSV entry.
     * @param string $uploaded_at MySQL date string representing the upload time, which is converted to ISO 8601 format.
     * @param string $access private or public
     * @param ?string $branchCountsString Optional JSON string representing branch entry counts; defaults to an empty string.
     *
     * @return array CSV row as an array of mapped values.
     */
    public function getMappedEntryCSV(string $JSONEntryString, mixed $userId, string $title, string $uploaded_at, string $access, ?string $branchCountsString = ''): array
    {
        $output = [];
        try {
            $JSONEntry = simdjson_decode($JSONEntryString, true);
            $JSONBranchCounts = simdjson_decode($branchCountsString, true);
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
        $this->addCreatedByIfNeeded($access, $output, $userId);
        //add title (fingers crossed)
        $output[] = $title; //title

        $answers = $entry['answers'];
        $inputsFlattened = $this->inputsFlattened;
        foreach ($inputsFlattened as $input) {
            $inputRef = $input['ref'];
            $inputMapping = $this->getInputMapping($inputRef);

            if (!$inputMapping['hide'] && $inputMapping['mapTo']) {

                $answer = $answers[$inputRef]['answer'] ?? '';

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

    /**
     * Constructs an ordered array of UUID headers for a branch entry.
     *
     * The first element is the owner entry UUID retrieved from the relationships data (defaults to an empty string if not available),
     * followed by the provided branch UUID. The order of these elements is critical for subsequent processing.
     *
     * @param string $uuid The branch entry's UUID.
     * @param array $relationships Associative array containing branch relationship data.
     *
     * @return array An array where the first element is the owner entry UUID and the second is the branch UUID.
     */
    private function getUUIDHeadersBranch(string $uuid, array $relationships): array
    {
        $out = [];
        //imp: order matters so don't change, otherwise need to change other places where header is ...
        $out[] = $relationships['branch']['data']['owner_entry_uuid'] ?? '';
        $out[] = $uuid;
        return $out;
    }

    /**
     * Transforms a JSON-encoded project entry into a structured JSON output.
     *
     * This function decodes the provided JSON entry and branch counts, then builds an output that includes UUID
     * headers (differentiated by form or branch type), ISO-formatted timestamps, a title, and optionally the creator's
     * email for private projects. It maps each input's answer according to its configuration and input type, handling
     * specialized parsing for branch, location, checkbox, searchsingle, and searchmultiple inputs.
     *
     * @param string $JSONEntryString A JSON-encoded string containing entry data and relationships.
     * @param mixed $userId The identifier used to retrieve the creator's email if needed.
     * @param string $title The title to include in the output.
     * @param string $uploaded_at The MySQL-formatted upload timestamp to be converted to ISO 8601 format.
     * @param string $access private or public
     * @param ?string $branchCountsString (Optional) A JSON-encoded string with branch count information.
     * @return false|array|string A JSON-encoded string of the mapped entry, an array if the entry data is missing,
     *                             or an empty string on JSON decoding failure.
     */
    public function getMappedEntryJSON(string $JSONEntryString, mixed $userId, string $title, string $uploaded_at, string $access, ?string $branchCountsString = ''): false|array|string
    {
        $output = [];
        try {
            $JSONEntry = simdjson_decode($JSONEntryString, true);
            $JSONBranchCounts = simdjson_decode($branchCountsString, true);
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
        // Add email as created_by column (only to private project downloads)
        $this->addCreatedByIfNeeded($access, $output, $userId);

        if ($entry == null) {
            return $output;
        }

        //add title (fingers crossed)
        $output['title'] = $title;

        $answers = $entry['answers'];
        $inputsFlattened = $this->inputsFlattened;
        foreach ($inputsFlattened as $input) {

            $inputRef = $input['ref'];
            $inputMapping = $this->getInputMapping($inputRef);

            if (!$inputMapping['hide'] && $inputMapping['mapTo']) {

                $answer = $answers[$inputRef]['answer'] ?? '';

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

    /**
     * Parses and converts an answer based on its input type.
     *
     * This method transforms a raw answer into a formatted value suitable for output. It handles various input types including:
     * - Selection inputs (e.g., radio, dropdown, search, and checkbox) by mapping answers through a possible answer mapping.
     * - Location data in CSV and JSON formats by converting latitude and longitude into UTM coordinates using a GPointConverter.
     * - Date and time values by formatting them according to a configured PHP datetime format.
     * - Media inputs (photo, video, audio) by generating an appropriate media URL.
     * - Numeric values by casting to integer or float.
     *
     * In cases where the expected location data is missing or invalid, the method returns fallback empty values while logging relevant information.
     *
     * @param mixed $type   The type identifier of the input (e.g., 'radio', 'csv-location', 'date', etc.).
     * @param mixed $answer The raw answer to convert; can be a string, array, or associative array with specific keys.
     * @param array $input  Metadata and configuration for the input, used for mapping and formatting.
     *
     * @return mixed The parsed answer, which may vary in type (string, array, integer, or float) depending on the input type.
     */
    private function parseAnswer(string $type, mixed $answer, array $input): mixed
    {
        $parsedAnswer = '';

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
                        $this->converter->setLongLat($answer['longitude'], $answer['latitude']);
                        $this->converter->convertLLtoTMClaude(null);

                        $parsedAnswer = [
                            $answer['latitude'],
                            $answer['longitude'],
                            $answer['accuracy'],
                            (int)$this->converter->N(),
                            (int)$this->converter->E(),
                            $this->converter->Z()
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
                    Log::info(
                        __METHOD__ . ' failed | csv-location, probably empty location',
                        [
                            'exception' => $e->getMessage(),
                            '$answer' => $answer
                        ]
                    );
                    //we get here when there is not an answer
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
                        $this->converter->setLongLat($answer['longitude'], $answer['latitude']);
                        $this->converter->convertLLtoTMClaude(null);

                        $parsedAnswer = [
                            'latitude' => $answer['latitude'],
                            'longitude' => $answer['longitude'],
                            'accuracy' => $answer['accuracy'],
                            'UTM_Northing' => (int)$this->converter->N(),
                            'UTM_Easting' => (int)$this->converter->E(),
                            'UTM_Zone' => $this->converter->Z()
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
    /**
     * Converts a MySQL date string to an ISO 8601 formatted timestamp.
     *
     * This method uses the Carbon library to parse the provided MySQL date and returns it
     * in an ISO 8601 format with milliseconds fixed to .000, emulating pre-Laravel 7 behavior.
     *
     * @param string $mysqlDate The MySQL date string to convert.
     * @return string The ISO 8601 formatted date string.
     */
    private function convertMYSQLDateToISO(string $mysqlDate): string
    {
        return Carbon::parse($mysqlDate)->format('Y-m-d\TH:i:s.000\Z');
    }

    /**
     * Adds the creator's email to the output array for private projects.
     *
     * When the project access is set to private, this function assigns a 'created_by' key in the output.
     * It defaults the value to "n/a" and, if a valid user ID is provided, attempts to retrieve the user's email
     * from a cache or database. The retrieved email is cached for future use.
     *
     * @param string $access The project's access level, used to determine whether to add the creator's email.
     * @param array  &$output Reference to the output array that will include the 'created_by' field if applicable.
     * @param mixed $userId The identifier of the user whose email should be retrieved.
     *
     */
    private function addCreatedByIfNeeded(string $access, array &$output, mixed $userId): void
    {
        if ($access === config('epicollect.strings.project_access.private')) {
            //userId can be  0 when the entry was uploaded when the project was public without logging in.
            $output['created_by'] = 'n/a';
            if ($userId) {
                if (isset($this->usersCache[$userId])) {
                    $output['created_by'] = $this->usersCache[$userId];
                } else {
                    $user = User::where('id', $userId)
                        ->where('state', '<>', 'archived')
                        ->first();
                    if ($user) {
                        $output['created_by'] = $user->email;
                        $this->usersCache[$userId] = $user->email;
                    } else {
                        $this->usersCache[$userId] = 'n/a';
                    }
                }
            }
        }
    }
}
