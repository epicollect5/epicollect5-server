<?php

namespace ec5\Http\Controllers\Api\Entries\View;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Http\Validation\Entries\Search\RuleQueryString;
use ec5\Http\Validation\Entries\Search\RuleQueryStringMapData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class EntriesLocationsController extends EntrySearchControllerBase
{

    /**
     * EntriesLocationsController constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param RuleQueryString $ruleQueryString
     * @param RuleAnswers $ruleAnswers
     */
    public function __construct(
        Request         $request,
        ApiRequest      $apiRequest,
        ApiResponse     $apiResponse,
        RuleQueryString $ruleQueryString,
        RuleAnswers     $ruleAnswers
    )
    {

        parent::__construct($request, $apiRequest, $apiResponse,
            $ruleQueryString, $ruleAnswers);

        $this->allowedSearchKeys = array_keys(config('epicollect.strings.search_data_entries'));
    }

    public function show(Request $request, RuleQueryStringMapData $ruleQueryStringMapData)
    {
        $columns = ['geo_json_data'];

        $options = $this->getRequestParams($request, config('epicollect.limits.entries_map.per_page'));

        // Validate the options
        $ruleQueryStringMapData->validate($options);
        if ($ruleQueryStringMapData->hasErrors()) {
            return Response::apiErrorCode(400, $ruleQueryStringMapData->errors());
        }
        // Do additional checks
        $ruleQueryStringMapData->additionalChecks($this->requestedProject(), $options);
        if ($ruleQueryStringMapData->hasErrors()) {
            return Response::apiErrorCode(400, $ruleQueryStringMapData->errors());
        }

        $repository = !empty($options['branch_ref']) ? $this->branchEntryRepository : $this->entryRepository;
        $project = $this->requestedProject();

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
