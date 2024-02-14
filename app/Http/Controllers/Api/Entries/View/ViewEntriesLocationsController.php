<?php

namespace ec5\Http\Controllers\Api\Entries\View;

use Carbon\Carbon;
use ec5\Http\Validation\Entries\View\RuleQueryStringLocations;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class ViewEntriesLocationsController extends ViewEntriesControllerBase
{
    /**
     * @param RuleQueryStringLocations $ruleQueryStringLocations
     * @return JsonResponse
     *
     * Get the entries' locations (geojson format) belonging to a question
     *
     * Hierarchy: form_ref (default to the top parent form), input_ref
     *
     * Branch: needs the branch_ref as well
     *
     *
     */
    public function show(RuleQueryStringLocations $ruleQueryStringLocations)
    {
        $allowedKeys = array_keys(config('epicollect.strings.search_data_entries'));
        $perPage = config('epicollect.limits.entries_map.per_page');
        $params = $this->entriesViewService->getSanitizedQueryParams($allowedKeys, $perPage);

        // Validate the payload params values
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

        $entries = $entryModel->getGeoJsonData($this->requestedProject()->getId(), $params);

        if ($entries->first() !== null) {
            $oldest = str_replace(' ', 'T', $entries->min('created_at')) . '.000Z';
            $newest = str_replace(' ', 'T', $entries->max('created_at')) . '.000Z';
        } else {
            //no entries, return today's date as ISO string
            $now = Carbon::now()->toIso8601String();
            $oldest = str_replace('+00:00', '.000Z', $now);
            $newest = str_replace('+00:00', '.000Z', $now);
        }

        $entriesPaginated = $entries->paginate($params['per_page']);

        // Data
        $data = [
            'id' => $this->requestedProject()->slug,
            'type' => 'geojson',
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => []
            ]
        ];

        // Loop and json decode the geo json data from the db
        foreach ($entriesPaginated as $entry) {
            // Add to the geo json features array if it is NOT NULL
            if (isset($entry->geo_json_data)) {
                $data['geojson']['features'][] = json_decode($entry->geo_json_data, true);
            }
        }

        // Append the required options to the LengthAwarePaginator
        $this->appendOptions($entriesPaginated, $params);
        // Get Meta and Links
        $meta = $this->getMeta($entriesPaginated, $newest, $oldest);
        $links = $this->getLinks($entriesPaginated);

        return Response::apiData($data, $meta, $links);
    }
}
