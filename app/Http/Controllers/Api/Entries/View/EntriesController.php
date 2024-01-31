<?php

namespace ec5\Http\Controllers\Api\Entries\View;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Http\Validation\Entries\Search\RuleQueryString;
use ec5\Services\DataMappingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntriesController extends EntrySearchControllerBase
{
    private $dataMappingService;

    /**
     * EntriesController constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param RuleQueryString $ruleQueryString
     * @param RuleAnswers $ruleAnswers
     */
    public function __construct(
        Request            $request,
        ApiRequest         $apiRequest,
        ApiResponse        $apiResponse,
        RuleQueryString    $ruleQueryString,
        RuleAnswers        $ruleAnswers,
        DataMappingService $dataMappingService
    )
    {

        parent::__construct(
            $request,
            $apiRequest,
            $apiResponse,
            $ruleQueryString,
            $ruleAnswers
        );

        $this->dataMappingService = $dataMappingService;
        $this->allowedSearchKeys = array_keys(config('epicollect.strings.search_data_entries'));
    }

    /**
     * @param Request $request
     * @return JsonResponse|StreamedResponse
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
        $this->dataMappingService->init(
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
                    return $this->sendBranchEntriesCSV($options);
                } else {
                    // Form
                    return $this->sendEntriesCSV($options);
                }
            default:
                // Branch
                if ($options['branch_ref'] != '') {
                    return $this->sendBranchEntriesJSON($options, true);
                }
                // Form
                return $this->sendEntriesJSON($options, true);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request)
    {
        $params = $this->getRequestParams($request, config('epicollect.limits.entries_table.per_page'));

        // Validate the params and query string
        if (!$this->validateParams($params)) {
            return $this->apiResponse->errorResponse(400, $this->validateErrors);
        }
        // Branch
        if ($params['branch_ref'] != '') {
            return $this->sendBranchEntriesJSON($params);
        }
        // Form
        return $this->sendEntriesJSON($params);
    }

    /**
     * @param array $options
     * @param bool $map
     * @return \Illuminate\Http\JsonResponse
     *
     */
    private function sendEntriesJSON(array $options, $map = false)
    {
        $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at', 'created_at'];
        $project = $this->requestedProject();
        $query = $this->runQueryHierarchy($options, $columns);

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
                    $this->dataMappingService->getMappedEntryJSON(
                        $row->entry_data,
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
     * @param array $params
     * @return JsonResponse|StreamedResponse
     */
    private function sendEntriesCSV(array $params)
    {
        $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at', 'created_at'];
        $query = $this->runQueryHierarchy($params, $columns);

        // Open the output stream
        $data = fopen('php://output', 'w');

        // Start output buffering (to capture stream contents)
        ob_start();

        // Add csv headers
        if ($params['headers'] == 'true') {
            fputcsv($data, $this->dataMappingService->getHeaderRowCSV(), ',');
        }

        $entries = $query->paginate($params['per_page']);
        try {
            foreach ($entries as $entry) {
                if (

                    !fputcsv($data, $this->dataMappingService->getMappedEntryCSV(
                        $entry->entry_data,
                        $entry->user_id,
                        $entry->title,
                        $entry->uploaded_at
                    ), ',')
                ) {
                    // Error writing to file
                    throw new Exception('Error writing file');
                }
            }
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return $this->apiResponse->errorResponse(400, ['entries-export-csv' => ['ec5_232']]);
        }

        return Response::toCSVStream($data);
    }

    /**
     * @param array $options
     * @param bool $map
     * @return \Illuminate\Http\JsonResponse
     */
    private function sendBranchEntriesJSON(array $options, $map = false)
    {
        $columns = ['title', 'entry_data', 'user_id', 'uploaded_at'];
        $project = $this->requestedProject();

        $query = $this->runQueryBranch($options, $columns);

        //get the newest and oldest dates of this subset (before pagination occurs)
        //and set the format to be like the one from JS for consistency
        $oldest = str_replace(' ', 'T', $query->min('created_at')) . '.000Z';
        $newest = str_replace(' ', 'T', $query->max('created_at')) . '.000Z';

        $entryData = $query->paginate($options['per_page']);

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
                    $this->dataMappingService->getMappedEntryJSON(
                        $row->entry_data,
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
     * @param array $params
     * @return JsonResponse|StreamedResponse
     */
    private function sendBranchEntriesCSV(array $params)
    {
        $columns = ['title', 'entry_data', 'user_id', 'uploaded_at'];

        $query = $this->runQueryBranch($params, $columns);

        // Open the output stream
        $data = fopen('php://output', 'w');

        // Start output buffering (to capture stream contents)
        ob_start();
        // Add csv headers
        if ($params['headers'] == 'true') {
            fputcsv($data, $this->dataMappingService->getHeaderRowCSV(), ',');
        }

        $entries = $query->paginate($params['per_page']);
        try {
            foreach ($entries as $entry) {
                if (
                    !fputcsv(
                        $data,
                        $this->dataMappingService->getMappedEntryCSV(
                            $entry->entry_data,
                            null,
                            $entry->user_id,
                            $entry->title,
                            $entry->uploaded_at),
                        ','
                    )
                ) {
                    throw new Exception('Error writing file');
                }
            }
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return $this->apiResponse->errorResponse(400, ['entries-export-csv' => ['ec5_232']]);
        }
        return Response::toCSVStream($data);
    }
}
