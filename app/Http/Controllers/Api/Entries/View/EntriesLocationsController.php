<?php

namespace ec5\Http\Controllers\Api\Entries\View;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;

use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Http\Validation\Entries\Search\RuleQueryString;
use ec5\Http\Validation\Entries\Search\RuleQueryStringMapData;

use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository;
use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository;

use Illuminate\Http\Request;
use Config;


class EntriesLocationsController extends EntrySearchControllerBase
{

    /**
     * EntriesLocationsController constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param EntryRepository $entryRepository
     * @param BranchEntryRepository $branchEntryRepository
     * @param RuleQueryString $ruleQueryString
     * @param RuleAnswers $ruleAnswers
     */
    public function __construct(
        Request $request,
        ApiRequest $apiRequest,
        ApiResponse $apiResponse,
        EntryRepository $entryRepository,
        BranchEntryRepository $branchEntryRepository,
        RuleQueryString $ruleQueryString,
        RuleAnswers $ruleAnswers
    ) {

        parent::__construct($request, $apiRequest, $apiResponse, $entryRepository,
            $branchEntryRepository, $ruleQueryString, $ruleAnswers);

        $this->allowedSearchKeys = Config::get('ec5Enums.search_data_entries');
    }

    /**
     * @param Request $request
     * @param RuleQueryStringMapData $ruleQueryStringMapData
     * @return ApiResponse|\Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *   path="/api/entries-locations/{project_slug}",
     *   summary="Get map locations for entries for a project",
     *   tags={"entries"},
     *   operationId="show",
     *     @SWG\Parameter(
     *     name="project_slug",
     *     in="path",
     *     description="The project slug.",
     *     required=true,
     *     type="string",
     *     default="ec5-demo-project"
     *   ),
     *   @SWG\Parameter(
     *     name="form_ref",
     *     in="query",
     *     description="The form ref.",
     *     required=true,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="input_ref",
     *     in="query",
     *     description="The input ref of the location input.",
     *     required=true,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="uuid",
     *     in="query",
     *     description="The uuid for a particular entry.",
     *     required=false,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="parent_form_ref",
     *     in="query",
     *     description="The parent form ref.",
     *     required=false,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="parent_uuid",
     *     in="query",
     *     description="The parent uuid.",
     *     required=false,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="branch_ref",
     *     in="query",
     *     description="The branch_ref (sometimes called owner_input_ref) for branch entries.",
     *     required=false,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="branch_owner_uuid",
     *     in="query",
     *     description="The branch owner uuid for branch entries.",
     *     required=false,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="per_page",
     *     in="query",
     *     description="The number of entries to show per page.",
     *     required=false,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="page",
     *     in="query",
     *     description="The current page of entries.",
     *     required=false,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="sort_order",
     *     in="query",
     *     description="The sort order for the entries.",
     *     required=false,
     *     type="string"
     *   ),
     *     @SWG\Parameter(
     *     name="entry_col",
     *     in="query",
     *     description="The column on which to sort.",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=400, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function show(Request $request, RuleQueryStringMapData $ruleQueryStringMapData)
    {
        $columns = ['geo_json_data'];

        $options = $this->getRequestOptions($request, Config::get('ec5Limits.entries_map.per_page'));

        // Validate the options
        $ruleQueryStringMapData->validate($options);
        if ($ruleQueryStringMapData->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $ruleQueryStringMapData->errors());
        }
        // Do additional checks
        $ruleQueryStringMapData->additionalChecks($this->requestedProject, $options);
        if ($ruleQueryStringMapData->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $ruleQueryStringMapData->errors());
        }

        $repository = (isset($options['branch_ref']) && !empty($options['branch_ref'])) ? $this->branchEntryRepository : $this->entryRepository;
        $project = $this->requestedProject;

        $entryData = $repository->getMapData($project->getId(), $options, $columns)->paginate($options['per_page']);

        // Data
        $data = [
            'id' => $project->slug,
            'type' => 'geojson',
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => []
            ]
        ];
        // todo: can this be optimised?
        // Loop and json decode the geo json data from the db
        foreach ($entryData as $row) {
            // Add to the geo json features array if it is NOT NULL
            if (isset($row->geo_json_data)) {
                $data['geojson']['features'][] = json_decode($row->geo_json_data, true);
            }
        }

        // Append the required options to the LengthAwarePaginator
        $this->appendOptions($entryData, $options);
        // Get Meta and Links
        $meta = $this->getMeta($entryData);
        $links = $this->getLinks($entryData);


        // Set up the json response
        $this->apiResponse->setMeta($meta);
        $this->apiResponse->setLinks($links);
        $this->apiResponse->setData($data);

        return $this->apiResponse->toJsonResponse(200);

    }

}
