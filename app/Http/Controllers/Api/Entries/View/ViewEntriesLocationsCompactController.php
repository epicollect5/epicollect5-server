<?php

namespace ec5\Http\Controllers\Api\Entries\View;

use ec5\Http\Validation\Entries\View\RuleQueryStringLocations;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ViewEntriesLocationsCompactController extends ViewEntriesControllerBase
{
    /**
     * @param RuleQueryStringLocations $ruleQueryStringLocations
     * @return JsonResponse
     *
     * Get the entries' locations in compact format belonging to a question
     *
     * Hierarchy: form_ref (default to the top parent form), input_ref
     *
     * Branch: needs the branch_ref as well
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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
        $entriesPaginated = $entries->paginate($params['per_page']);
        $dates = $entryModel->getNewestOldestCreatedAt($this->requestedProject()->getId(), $params['form_ref']);

        $data = [
            'input_ref' => $params['input_ref'],
            'pa_map' => [],
            'points' => []
        ];

        $possibleAnswerIndexByRef = [];
        $inputRef = $params['input_ref'];
        foreach ($entriesPaginated as $entry) {
            if (!isset($entry->geo_json_data)) {
                continue;
            }

            $geoJSON = simdjson_decode($entry->geo_json_data, true);
            if (!isset($geoJSON[$inputRef])) {
                continue;
            }

            $point = $this->compactGeoJsonFeature(
                $geoJSON[$inputRef],
                $data['pa_map'],
                $possibleAnswerIndexByRef
            );

            if ($point !== null) {
                $data['points'][] = $point;
            }
        }
        // Append the required options to the LengthAwarePaginator
        $this->appendOptions($entriesPaginated, $params);
        // Get Meta and Links
        $meta = $this->getMeta($entriesPaginated, $dates['newest'], $dates['oldest']);

        $links = $this->getLinks($entriesPaginated);

        return Response::apiData($data, $meta, $links);
    }

    private function compactGeoJsonFeature(
        array $feature,
        array &$possibleAnswerMap,
        array &$possibleAnswerIndexByRef
    ): ?array {
        if (!$this->hasRequiredLocationFields($feature)) {
            return null;
        }

        $possibleAnswerIndexes = [];
        foreach ($feature['properties']['possible_answers'] ?? [] as $possibleAnswerRef => $selected) {
            if (!$selected) {
                continue;
            }

            if (!isset($possibleAnswerIndexByRef[$possibleAnswerRef])) {
                $possibleAnswerIndexByRef[$possibleAnswerRef] = count($possibleAnswerMap);
                $possibleAnswerMap[] = $possibleAnswerRef;
            }

            $possibleAnswerIndexes[] = $possibleAnswerIndexByRef[$possibleAnswerRef];
        }

        return [
            'u' => $feature['id'],
            'x' => $feature['geometry']['coordinates'][0],
            'y' => $feature['geometry']['coordinates'][1],
            'd' => (int) date('Ymd', strtotime($feature['properties']['created_at'])),
            'pa' => $possibleAnswerIndexes,
        ];
    }

    private function hasRequiredLocationFields(array $feature): bool
    {
        return isset(
            $feature['id'],
            $feature['geometry']['coordinates'][0],
            $feature['geometry']['coordinates'][1],
            $feature['properties']['created_at']
        );
    }
}
