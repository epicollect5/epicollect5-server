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
        $uiPerPage = (int) config('epicollect.limits.entries_map.per_page');
        $chunkPerPage = (int) config('epicollect.limits.entries_map.per_chunk');
        $params = $this->entriesViewService->getSanitizedQueryParams($allowedKeys, $uiPerPage);
        $params['per_page'] = $chunkPerPage;

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
        $possibleAnswerMap = $this->getPossibleAnswerMap($params);
        $possibleAnswerIndexByRef = array_flip($possibleAnswerMap);

        $data = [
            'input_ref' => $params['input_ref'],
            'pa_map' => $possibleAnswerMap,
            'points' => []
        ];

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
                $possibleAnswerIndexByRef
            );

            if ($point !== null) {
                $data['points'][] = $point;
            }
        }
        // Append the required options to the LengthAwarePaginator
        $this->appendOptions($entriesPaginated, $params);
        // Get Meta and Links
        $meta = $this->getCompactMeta(
            $entriesPaginated,
            $dates['newest'],
            $dates['oldest'],
            $uiPerPage,
            $chunkPerPage
        );

        $links = $this->getCompactLinks($entriesPaginated);

        return Response::apiData($data, $meta, $links);
    }

    private function compactGeoJsonFeature(
        array $feature,
        array $possibleAnswerIndexByRef
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
                continue;
            }

            $possibleAnswerIndexes[] = $possibleAnswerIndexByRef[$possibleAnswerRef];
        }

        return [
            'u' => $feature['id'],
            'x' => $feature['geometry']['coordinates'][0],
            'y' => $feature['geometry']['coordinates'][1],
            'd' => (int) gmdate('Ymd', strtotime($feature['properties']['created_at'])),
            'pa' => $possibleAnswerIndexes,
        ];
    }

    private function getPossibleAnswerMap(array $params): array
    {
        return $this->requestedProject()
            ->getProjectExtra()
            ->getPossibleAnswerRefs($params['form_ref'], $params['branch_ref'] ?? null);
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

    private function getCompactMeta(
        $entriesPaginated,
        ?string $newest,
        ?string $oldest,
        int $uiPerPage,
        int $chunkPerPage
    ): array {
        $meta = $this->getMeta($entriesPaginated, $newest, $oldest);
        $chunkCount = max(1, (int) ceil($uiPerPage / $chunkPerPage));
        $currentPage = max(1, (int) ceil($entriesPaginated->currentPage() / $chunkCount));
        $lastPage = max(1, (int) ceil($entriesPaginated->total() / $uiPerPage));

        $meta['per_page'] = $uiPerPage;
        $meta['current_page'] = $currentPage;
        $meta['last_page'] = $lastPage;
        $meta['from'] = $currentPage;
        $meta['to'] = $lastPage;
        $meta['per_chunk'] = $chunkPerPage;
        $meta['chunk_page'] = $entriesPaginated->currentPage();
        $meta['chunk_last_page'] = $entriesPaginated->lastPage();

        return $meta;
    }

    private function getCompactLinks($entriesPaginated): array
    {
        return [
            'self' => $entriesPaginated->url($entriesPaginated->currentPage()),
            'prev' => $entriesPaginated->previousPageUrl(),
            'next' => $entriesPaginated->nextPageUrl(),
        ];
    }
}
