<?php

namespace ec5\Http\Controllers\Api\Entries\View;

use ec5\Http\Validation\Entries\Search\RuleQueryStringLocations;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use Illuminate\Support\Facades\Response;

class ViewEntriesLocationsController extends ViewEntriesControllerBase
{
    public function show(RuleQueryStringLocations $ruleQueryStringLocations)
    {
        $allowedKeys = array_keys(config('epicollect.strings.search_data_entries'));
        $perPage = config('epicollect.limits.entries_map.per_page');
        $params = $this->entriesViewService->getSanitizedQueryParams($allowedKeys, $perPage);

        // Validate the params
        $ruleQueryStringLocations->validate($params);
        if ($ruleQueryStringLocations->hasErrors()) {
            return Response::apiErrorCode(400, $ruleQueryStringLocations->errors());
        }
        // Do additional checks
        $ruleQueryStringLocations->additionalChecks($this->requestedProject(), $params);
        if ($ruleQueryStringLocations->hasErrors()) {
            return Response::apiErrorCode(400, $ruleQueryStringLocations->errors());
        }

        if (!empty($params['branch_ref'])) {
            //this is a branch entry
            $entryModel = new BranchEntry();
        } else {
            // this is a hierarchy
            $entryModel = new Entry();
        }

        $entryData = $entryModel->getGeoJsonData($this->requestedProject()->getId(), $params)
            ->paginate($params['per_page']);

        // Data
        $data = [
            'id' => $this->requestedProject()->slug,
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
        $this->appendOptions($entryData, $params);
        // Get Meta and Links
        $meta = $this->getMeta($entryData);
        $links = $this->getLinks($entryData);

        return Response::apiData($data, $meta, $links);
    }
}
