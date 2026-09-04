<?php

namespace Tests\Http\Controllers\Api\Entries\View\Internal;

use ec5\Libraries\Utilities\Common;
use ec5\Models\Entries\Entry;
use ec5\Traits\Assertions;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Http\Controllers\Api\Entries\View\ViewEntriesBaseControllerTest;
use Throwable;

class ViewEntriesLocationsCompactControllerTest extends ViewEntriesBaseControllerTest
{
    use DatabaseTransactions;
    use Assertions;

    /**
     * @throws Throwable
     */
    public function test_entries_locations_compact_endpoint_form_0_single_entry()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );

        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );

        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);
        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $locationInputRefs[0];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations-compact/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsCompactResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $data = $json['data'];
            $meta = $json['meta'];

            $entry = Entry::where('uuid', $entryPayload['data']['id'])->first();
            $geoJSON = json_decode($entry->geo_json_data, true);
            $feature = $geoJSON[$locationInputRefs[0]];

            $this->assertArrayNotHasKey('geojson', $data);
            $this->assertEquals($locationInputRefs[0], $data['input_ref']);
            $this->assertEquals(1, sizeof($data['points']));
            $this->assertEquals(config('epicollect.limits.entries_map.per_page'), $meta['per_page']);
            $this->assertEquals(config('epicollect.limits.entries_map.per_chunk'), $meta['per_chunk']);
            $this->assertEquals(1, $meta['current_page']);
            $this->assertEquals(1, $meta['last_page']);
            $this->assertEquals(1, $meta['chunk_page']);
            $this->assertEquals(1, $meta['chunk_last_page']);
            $this->assertCompactPointMatchesFeature($data['points'][0], $feature, $data['pa_map']);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_entries_locations_compact_endpoint_uses_stable_possible_answer_map_across_chunks()
    {
        config()->set('epicollect.limits.entries_map.per_page', 2);
        config()->set('epicollect.limits.entries_map.per_chunk', 1);

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);
        $possibleAnswerMap = $this->getFormPossibleAnswerRefs($formRef);

        $this->assertGreaterThanOrEqual(2, count($possibleAnswerMap));

        $firstEntryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $firstEntryPayload
        );

        $secondEntryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $secondEntryPayload
        );

        $this->setEntryGeoJsonPossibleAnswers(
            $firstEntryPayload['data']['id'],
            $locationInputRefs[0],
            [$possibleAnswerMap[0] => 1]
        );
        $this->setEntryGeoJsonPossibleAnswers(
            $secondEntryPayload['data']['id'],
            $locationInputRefs[0],
            [$possibleAnswerMap[1] => 1]
        );

        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $locationInputRefs[0];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations-compact/' . $this->project->slug . $queryString . '&page=1');
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsCompactResponse($response[0]);

            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations-compact/' . $this->project->slug . $queryString . '&page=2');
            $response[1]->assertStatus(200);
            $this->assertEntriesLocationsCompactResponse($response[1]);

            $firstPageData = json_decode($response[0]->getContent(), true)['data'];
            $secondPageData = json_decode($response[1]->getContent(), true)['data'];

            $this->assertEquals($possibleAnswerMap, $firstPageData['pa_map']);
            $this->assertEquals($possibleAnswerMap, $secondPageData['pa_map']);
            $this->assertContains(0, $firstPageData['points'][0]['pa']);
            $this->assertContains(1, $secondPageData['points'][0]['pa']);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    private function assertEntriesLocationsCompactResponse($response): void
    {
        $response->assertJsonStructure([
            'data' => [
                'input_ref',
                'pa_map',
                'points' => [
                    '*' => [
                        'u',
                        'x',
                        'y',
                        'd',
                        'pa' => []
                    ]
                ],
            ],
            'meta' => [
                'total',
                'per_page',
                'current_page',
                'last_page',
                'from',
                'to',
                'newest',
                'oldest',
                'per_chunk',
                'chunk_page',
                'chunk_last_page'
            ],
            'links' => [
                'self',
                'prev',
                'next'
            ]
        ]);
    }

    private function assertCompactPointMatchesFeature(array $point, array $feature, array $possibleAnswerMap): void
    {
        $this->assertEquals($feature['id'], $point['u']);
        $this->assertEquals($feature['geometry']['coordinates'][0], $point['x']);
        $this->assertEquals($feature['geometry']['coordinates'][1], $point['y']);
        $this->assertEquals((int) gmdate('Ymd', strtotime($feature['properties']['created_at'])), $point['d']);

        $possibleAnswerRefs = [];
        foreach ($point['pa'] as $possibleAnswerIndex) {
            $this->assertArrayHasKey($possibleAnswerIndex, $possibleAnswerMap);
            $possibleAnswerRefs[] = $possibleAnswerMap[$possibleAnswerIndex];
        }

        $expectedPossibleAnswerRefs = array_keys(array_filter($feature['properties']['possible_answers']));
        $this->assertEquals($expectedPossibleAnswerRefs, $possibleAnswerRefs);
    }

    private function getFormPossibleAnswerRefs(string $formRef): array
    {
        $multipleChoiceInputs = array_get(
            $this->projectExtra,
            'forms.' . $formRef . '.lists.multiple_choice_inputs.form',
            []
        );
        $possibleAnswerRefs = [];
        $inputRefs = $multipleChoiceInputs['order'] ?? [];

        foreach ($inputRefs as $inputRef) {
            foreach (array_keys($multipleChoiceInputs[$inputRef]['possible_answers'] ?? []) as $possibleAnswerRef) {
                $possibleAnswerRefs[] = $possibleAnswerRef;
            }
        }

        return array_values(array_unique($possibleAnswerRefs));
    }

    private function setEntryGeoJsonPossibleAnswers(string $entryUuid, string $inputRef, array $possibleAnswers): void
    {
        $entry = Entry::where('uuid', $entryUuid)->first();
        $geoJson = json_decode($entry->geo_json_data, true);
        $geoJson[$inputRef]['properties']['possible_answers'] = $possibleAnswers;
        $entry->geo_json_data = json_encode($geoJson);
        $entry->save();
    }
}
