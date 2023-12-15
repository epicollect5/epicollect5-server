<?php

namespace ec5\Http\Controllers\Api\Entries\View;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Http\Validation\Entries\Search\RuleQueryString;
use ec5\Models\ProjectData\DataMappingHelper;
use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository;
use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EntriesController extends EntrySearchControllerBase
{
    /**
     * @var DataMappingHelper
     */
    protected $dataMappingHelper;

    /**
     *  Create a new api view entry controller instance.
     *
     * @param EntryRepository $entryRepository
     * @param BranchEntryRepository $branchEntryRepository
     */

    /**
     * EntriesController constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param EntryRepository $entryRepository
     * @param BranchEntryRepository $branchEntryRepository
     * @param RuleQueryString $ruleQueryString
     * @param RuleAnswers $ruleAnswers
     * @param DataMappingHelper $dataMappingHelper
     */
    public function __construct(
        Request               $request,
        ApiRequest            $apiRequest,
        ApiResponse           $apiResponse,
        EntryRepository       $entryRepository,
        BranchEntryRepository $branchEntryRepository,
        RuleQueryString       $ruleQueryString,
        RuleAnswers           $ruleAnswers,
        DataMappingHelper     $dataMappingHelper
    )
    {

        parent::__construct(
            $request,
            $apiRequest,
            $apiResponse,
            $entryRepository,
            $branchEntryRepository,
            $ruleQueryString,
            $ruleAnswers
        );

        $this->allowedSearchKeys = array_keys(config('epicollect.strings.search_data_entries'));
        $this->dataMappingHelper = $dataMappingHelper;
    }

    /**
     * @param Request $request
     * @return ApiResponse|\Illuminate\Http\JsonResponse|null
     */
    public function export(Request $request)
    {
        $jsonPerPageLimit = config('epicollect.limits.entries_export_per_page_json');
        $csvPerPageLimit = config('epicollect.limits.entries_export_per_page_csv');

        // Check the mapping is valid
        $projectMapping = $this->requestedProject()->getProjectMapping();

        $options = $this->getRequestParams($request, config('epicollect.limits.entries_table.per_page'));

        // Validate the options and query string
        if (!$this->validateParams($options)) {
            return $this->apiResponse->errorResponse(400, $this->validateErrors);
        }

        // If the map_index value passed does not exist, error out
        if (!in_array($options['map_index'], $projectMapping->getMapIndexes())) {
            return $this->apiResponse->errorResponse(400, ['map_index: ' . $options['map_index'] => ['ec5_322']]);
        }

        //if per_page is over the limit, bail out
        if (isset($options['per_page'])) {

            //check format, csv has a higher limit
            if (isset($options['format'])) {
                if ($options['format'] === 'csv') {
                    //csv format
                    if ($options['per_page'] > $csvPerPageLimit) {
                        return $this->apiResponse->errorResponse(400, ['api' => ['ec5_335']]);
                    }
                }
                if ($options['format'] === 'json') {
                    //json format
                    if ($options['per_page'] > $jsonPerPageLimit) {
                        return $this->apiResponse->errorResponse(400, ['api' => ['ec5_335']]);
                    }
                }
            } else {
                //json format
                if ($options['per_page'] > $jsonPerPageLimit) {
                    return $this->apiResponse->errorResponse(400, ['api' => ['ec5_335']]);
                }
            }
        }
        // Set the mapping
        $this->dataMappingHelper->initialiseMapping(
            $this->requestedProject(),
            $options['format'],
            $options['branch_ref'] !== '' ? 'branch' : 'form',
            $options['form_ref'],
            $options['branch_ref'],
            $options['map_index']
        );

        // Switch on the format
        switch ($options['format']) {
            case 'csv':
                // Branch
                if ($options['branch_ref'] != '') {
                    $this->getBranchEntriesCsv($options);
                } else {
                    // Form
                    $this->getEntriesCSV($options);

                    if (count($this->errors) > 0) {
                        return $this->apiResponse->errorResponse(400, $this->errors);
                    }
                }

                break;
            default:
                // Branch
                if ($options['branch_ref'] != '') {
                    return $this->getBranchEntriesJson($options, true);
                }
                // Form
                return $this->getEntriesJSON($options, true);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request)
    {
        $options = $this->getRequestParams($request, config('epicollect.limits.entries_table.per_page'));

        // Validate the options and query string
        if (!$this->validateParams($options)) {
            return $this->apiResponse->errorResponse(400, $this->validateErrors);
        }

        // Branch
        if ($options['branch_ref'] != '') {
            return $this->getBranchEntriesJson($options);
        }
        // Form
        return $this->getEntriesJSON($options);
    }

    /**
     * @param array $options
     * @param bool $map
     * @return \Illuminate\Http\JsonResponse
     */
    private function getEntriesJSON(array $options, $map = false)
    {
        $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at', 'created_at'];
        $project = $this->requestedProject();
        $query = $this->runQuery($options, $columns);

        //get newest and oldest dates of this subset (before pagination occurs)
        //and set the format to be like the one from JS for consistency
        //like 2020-12-10T11:31:30.000Z
        if ($query->first() !== null) {
            $oldest = str_replace(' ', 'T', $query->min('created_at')) . '.000Z';
            $newest = str_replace(' ', 'T', $query->max('created_at')) . '.000Z';
        } else {
            //no entries, return todays' date as ISO string
            $now = Carbon::now()->toIso8601String();
            $oldest = str_replace('+00:00', '.000Z', $now);
            $newest = str_replace('+00:00', '.000Z', $now);
        }

        $entryData = $query->paginate($options['per_page']);

        // Data
        $data = [
            'id' => $project->slug,
            'type' => 'entries',
            'entries' => []
        ];

        // todo: can this be optimised?
        // Loop and json decode the json data from the db
        foreach ($entryData as $row) {

            // Add to the json
            $entry = json_decode($row->entry_data, true);

            // Map the entries?
            if ($map) {

                $data['entries'][] = json_decode(
                    $this->dataMappingHelper->swapOutEntryJson(
                        $row->entry_data,
                        $row->branch_counts ?? null,
                        $row->user_id,
                        $row->title,
                        $row->uploaded_at
                    ),
                    true
                );
                $projectMapping = $this->requestedProject()->getProjectMapping();
                $data['mapping'] = $projectMapping->getMapDetails($options['map_index']);
            } else {
                $entry['attributes']['branch_counts'] = json_decode($row->branch_counts, true);
                $entry['attributes']['child_counts'] = json_decode($row->child_counts, true);
                // Add the user id
                $entry['relationships']['user']['data']['id'] = $row->user_id;
                $data['entries'][] = $entry;
            }
        }

        // Append the required options to the LengthAwarePaginator
        $this->appendOptions($entryData, $options);
        // Get Meta and Links
        $meta = $this->getMeta($entryData, $newest, $oldest);
        $links = $this->getLinks($entryData);


        // Set up the json response
        $this->apiResponse->setMeta($meta);
        $this->apiResponse->setLinks($links);

        $this->apiResponse->setData($data);


        return $this->apiResponse->toJsonResponse(200);
    }

    /**
     * @param array $options
     */
    private function getEntriesCSV(array $options)
    {
        $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at', 'created_at'];
        $query = $this->runQuery($options, $columns);

        // Open the output stream
        $data = fopen('php://output', 'w');

        // Start output buffering (to capture stream contents)
        ob_start();

        // Add csv headers
        if ($options['headers'] == 'true') {
            fputcsv($data, $this->dataMappingHelper->headerRowCsv(), ',');
        }

        $entries = $query->paginate($options['per_page']);

        foreach ($entries as $entry) {

            if (
                fputcsv($data, $this->dataMappingHelper->swapOutEntryCsv(
                    $entry->entry_data,
                    $entry->branch_counts ?? null,
                    $entry->user_id,
                    $entry->title,
                    $entry->uploaded_at
                ), ',') === false
            ) {
                // Error writing to file
                $this->errors['entries-export-csv'] = ['ec5_232'];
                return;
            }
        }

        $this->apiResponse->toCsvResponse($data);
    }

    /**
     * @param array $options
     * @param bool $map
     * @return \Illuminate\Http\JsonResponse
     */
    private function getBranchEntriesJson(array $options, $map = false)
    {
        $columns = ['title', 'entry_data', 'user_id', 'uploaded_at'];
        $project = $this->requestedProject();

        $query = $this->runQueryBranch($options, $columns);

        //get newest and oldest dates of this subset (before pagination occurs)
        //and set the format to be like the one from JS for consistency
        $oldest = str_replace(' ', 'T', $query->min('created_at')) . '.000Z';
        $newest = str_replace(' ', 'T', $query->max('created_at')) . '.000Z';

        $entryData = $query->paginate($options['per_page']);

        // Data
        $data = [
            'id' => $project->slug,
            'type' => 'entries',
            'entries' => []
        ];
        // todo: can this be optimised?
        // Loop and json decode the json data from the db
        foreach ($entryData as $row) {
            // Add to the json
            $entry = json_decode($row->entry_data, true);

            // Map the entries?
            if ($map) {
                $data['entries'][] = json_decode(
                    $this->dataMappingHelper->swapOutEntryJson(
                        $row->entry_data,
                        $row->branch_counts ?? null,
                        $row->user_id,
                        $row->title,
                        $row->uploaded_at
                    ),
                    true
                );
                $projectMapping = $this->requestedProject()->getProjectMapping();
                $data['mapping'] = $projectMapping->getMapDetails($options['map_index']);
            } else {
                // Add the user id
                $entry['relationships']['user']['data']['id'] = $row->user_id;
                $data['entries'][] = $entry;
            }
        }

        // Append the required options to the LengthAwarePaginator
        $this->appendOptions($entryData, $options);
        // Get Meta and Links
        $meta = $this->getMeta($entryData, $newest, $oldest);
        $links = $this->getLinks($entryData);

        // Set up the json response
        $this->apiResponse->setMeta($meta);
        $this->apiResponse->setLinks($links);
        $this->apiResponse->setData($data);

        return $this->apiResponse->toJsonResponse(200);
    }

    /**
     * @param array $options
     */
    private function getBranchEntriesCsv(array $options)
    {
        $columns = ['title', 'entry_data', 'user_id', 'uploaded_at'];

        $query = $this->runQueryBranch($options, $columns);

        // Open the output stream
        $data = fopen('php://output', 'w');

        // Start output buffering (to capture stream contents)
        ob_start();

        // Add csv headers
        if ($options['headers'] == 'true') {
            fputcsv($data, $this->dataMappingHelper->headerRowCsv(), ',');
        }

        $entries = $query->paginate($options['per_page']);


        foreach ($entries as $entry) {

            if (
                fputcsv(
                    $data,
                    $this->dataMappingHelper->swapOutEntryCsv($entry->entry_data, null, $entry->user_id, $entry->title, $entry->uploaded_at),
                    ','
                ) === false
            ) {
                // Error writing to file
                $this->errors['entries-export-csv'] = ['ec5_232'];
                return;
            }
        }

        $this->apiResponse->toCsvResponse($data);
    }
}
