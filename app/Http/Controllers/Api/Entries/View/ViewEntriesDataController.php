<?php

namespace ec5\Http\Controllers\Api\Entries\View;

use ec5\Libraries\Utilities\DateFormatConverter;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ViewEntriesDataController extends ViewEntriesControllerBase
{
    /**
     * @return JsonResponse|StreamedResponse
     */
    public function export()
    {
        $jsonPerPageLimit = config('epicollect.limits.entries_export_per_page_json');
        $csvPerPageLimit = config('epicollect.limits.entries_export_per_page_csv');

        // Check the mapping is valid
        $projectMapping = $this->requestedProject()->getProjectMapping();

        $allowedKeys = array_keys(config('epicollect.strings.search_data_entries'));
        $perPage = config('epicollect.limits.entries_table.per_page');
        $params = $this->entriesViewService->getSanitizedQueryParams($allowedKeys, $perPage);

        // Validate the options and query string
        if (!$this->entriesViewService->areValidQueryParams($params)) {
            return Response::apiErrorCode(400, $this->entriesViewService->validationErrors);
        }

        // If the map_index value passed does not exist, error out
        if (!in_array($params['map_index'], $projectMapping->getMapIndexes())) {
            return Response::apiErrorCode(400, ['map_index: ' . $params['map_index'] => ['ec5_322']]);
        }

        //if per_page is over the limit, bail out
        if (isset($params['per_page'])) {

            //check format, csv has a higher limit
            if (isset($params['format'])) {
                if ($params['format'] === 'csv') {
                    //csv format
                    if ($params['per_page'] > $csvPerPageLimit) {
                        return Response::apiErrorCode(400, ['api' => ['ec5_335']]);
                    }
                }
                if ($params['format'] === 'json') {
                    //json format
                    if ($params['per_page'] > $jsonPerPageLimit) {
                        return Response::apiErrorCode(400, ['api' => ['ec5_335']]);
                    }
                }
            } else {
                //json format
                if ($params['per_page'] > $jsonPerPageLimit) {
                    return Response::apiErrorCode(400, ['api' => ['ec5_335']]);
                }
            }
        }
        // Set the mapping
        $this->dataMappingService->init(
            $this->requestedProject(),
            $params['format'],
            $params['branch_ref'] !== '' ? 'branch' : 'form',
            $params['form_ref'],
            $params['branch_ref'],
            $params['map_index']
        );

        // Switch on the format
        switch ($params['format']) {
            case 'csv':
                // Branch
                if ($params['branch_ref'] != '') {
                    return $this->sendBranchEntriesCSV($params);
                } else {
                    // Form
                    return $this->sendEntriesCSV($params);
                }
                // no break
            default:
                // Branch
                if ($params['branch_ref'] != '') {
                    return $this->sendBranchEntriesJSON($params, true);
                }
                // Form
                return $this->sendEntriesJSON($params, true);
        }
    }

    /**
     * @return JsonResponse
     */
    public function show()
    {
        $allowedKeys = array_keys(config('epicollect.strings.search_data_entries'));
        $perPage = config('epicollect.limits.entries_table.per_page');
        $params = $this->entriesViewService->getSanitizedQueryParams($allowedKeys, $perPage);

        // Validate the params and query string
        if (!$this->entriesViewService->areValidQueryParams($params)) {
            return Response::apiErrorCode(400, $this->entriesViewService->validationErrors);
        }
        // Branch
        if ($params['branch_ref'] != '') {
            return $this->sendBranchEntriesJSON($params);
        }
        // Form
        return $this->sendEntriesJSON($params);
    }

    /**
     * Retrieves and formats entries data in JSON format.
     *
     * Executes a hierarchical query based on the provided options and paginates the results. Each entry is processed by decoding its stored JSON and, if mapping is enabled, reformatting it using the project-specific mapping service. The response includes the project's slug, a data type indicator, the formatted entries, metadata (including the newest and oldest entry dates), and pagination links.
     *
     * @param array $options Array of query options; expected keys include 'per_page' for pagination and 'map_index' for obtaining mapping details.
     * @param bool $map Determines whether to apply project-specific mapping to the entries.
     * @return JsonResponse The JSON response containing the formatted entries data, along with metadata and pagination links.
     */
    private function sendEntriesJSON(array $options, bool $map = false)
    {
        $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at', 'created_at'];
        $project = $this->requestedProject();
        $access = $project->access;
        $query = $this->runQueryHierarchy($options, $columns);

        //get the newest and oldest dates of this subset (before pagination occurs)
        //and set the format to be like the one from JS for consistency
        //like 2020-12-10T11:31:30.000Z
        $dates = DateFormatConverter::getNewestAndOldestFormatted($query);

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
                        $row->uploaded_at,
                        $access,
                        $row->branch_counts ?? null
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
        $meta = $this->getMeta($entryData, $dates['newest'], $dates['oldest']);
        $links = $this->getLinks($entryData);

        return Response::apiData($data, $meta, $links);
    }

    /**
     * @param array $params
     * @return ResponseFactory|\Illuminate\Foundation\Application|JsonResponse|\Illuminate\Http\Response
     */
    private function sendEntriesCSV(array $params)
    {
        $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at', 'created_at'];
        $query = $this->runQueryHierarchy($params, $columns);

        return $this->sendCSVResponse($query, $params);
    }

    /**
     * Retrieves and formats branch entries into a paginated JSON response.
     *
     * Executes a branch-specific query based on input parameters, formats the results,
     * and optionally applies a data mapping transformation using the project's access level.
     * The response includes each entry’s title, entry data, associated user, and upload timestamp,
     * along with pagination metadata and links. When mapping is enabled, additional mapping details
     * are provided based on the specified map index.
     *
     * @param array $params An array of parameters controlling query options and pagination.
     *                      Expected keys include 'per_page' for the number of entries per page,
     *                      and 'map_index' for retrieving specific mapping details when mapping is enabled.
     * @param bool $map Flag indicating whether to apply data mapping to the branch entries.
     *
     * @return JsonResponse The JSON response containing the paginated branch entries, with associated metadata and links.
     */
    private function sendBranchEntriesJSON(array $params, bool $map = false)
    {
        $columns = ['title', 'entry_data', 'user_id', 'uploaded_at'];
        $project = $this->requestedProject();
        $access = $project->access;

        $branchEntries = $this->runQueryBranch($params, $columns);

        //get the newest and oldest dates of this subset (before pagination occurs)
        //and set the format to be like the one from JS for consistency
        $dates = DateFormatConverter::getNewestAndOldestFormatted($branchEntries);

        $branchEntriesPaginated = $branchEntries->paginate($params['per_page']);

        $data = [
            'id' => $project->slug,
            'type' => 'entries',
            'entries' => []
        ];
        // todo: can this be optimised?
        // Loop and json decode the json data from the db
        foreach ($branchEntriesPaginated as $row) {
            // Add to the json
            $entry = json_decode($row->entry_data, true);
            // Map the entries?
            if ($map) {
                $data['entries'][] = json_decode(
                    $this->dataMappingService->getMappedEntryJSON(
                        $row->entry_data,
                        $row->user_id,
                        $row->title,
                        $row->uploaded_at,
                        $access,
                        $row->branch_counts ?? null
                    ),
                    true
                );
                $projectMapping = $this->requestedProject()->getProjectMapping();
                $data['mapping'] = $projectMapping->getMapDetails($params['map_index']);
            } else {
                // Add the user id
                $entry['relationships']['user']['data']['id'] = $row->user_id;
                $data['entries'][] = $entry;
            }
        }

        // Append the required options to the LengthAwarePaginator
        $this->appendOptions($branchEntriesPaginated, $params);
        // Get Meta and Links
        $meta = $this->getMeta($branchEntriesPaginated, $dates['newest'], $dates['oldest']);
        $links = $this->getLinks($branchEntriesPaginated);

        return Response::apiData($data, $meta, $links);
    }

    /**
     * @param array $params
     * @return Application|ResponseFactory|JsonResponse|\Illuminate\Http\Response
     */
    private function sendBranchEntriesCSV(array $params)
    {
        $columns = ['title', 'entry_data', 'user_id', 'uploaded_at'];

        $query = $this->runQueryBranch($params, $columns);

        return $this->sendCSVResponse($query, $params);
    }

    /**
     * Streams a CSV export response containing entry data.
     *
     * This function opens an output stream and, if specified, writes a header row
     * to the CSV. It then paginates through the provided query results and writes each
     * entry to the CSV using a data mapping service that incorporates the project's access level.
     * If an error occurs while writing any entry, the function logs the error and returns an API
     * error response with a 400 status code.
     *
     * @param Builder $query The query object used to retrieve and paginate entry data.
     * @param array $params Array of CSV export parameters including:
     *                      - 'headers' (string): Set to 'true' to output the CSV header row.
     *                      - 'per_page' (int): Number of entries per page for pagination.
     *
     * @return mixed A CSV stream response on success, or an API error response if an error occurs.
     */
    private function sendCSVResponse(Builder $query, array $params)
    {
        $access = $this->requestedProject()->access;
        // Open the output stream
        $data = fopen('php://output', 'w');

        // Start output buffering (to capture stream contents)
        ob_start();
        // Add csv headers
        if ($params['headers'] == 'true') {
            fputcsv($data, $this->dataMappingService->getHeaderRowCSV());
        }

        $entries = $query->paginate($params['per_page']);

        try {
            foreach ($entries as $entry) {
                if (
                    !fputcsv(
                        $data,
                        $this->dataMappingService->getMappedEntryCSV(
                            $entry->entry_data,
                            $entry->user_id,
                            $entry->title,
                            $entry->uploaded_at,
                            $access,
                            $entry->branch_counts ?? null
                        )
                    )
                ) {
                    throw new Exception('Error writing file');
                }
            }
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return Response::apiErrorCode(400, ['entries-export-csv' => ['ec5_232']]);
        }
        return Response::toCSVStream();
    }
}
