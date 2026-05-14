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

            $entry = Entry::where('uuid', $entryPayload['data']['id'])->first();
            $geoJSON = json_decode($entry->geo_json_data, true);
            $feature = $geoJSON[$locationInputRefs[0]];

            $this->assertArrayNotHasKey('geojson', $data);
            $this->assertEquals($locationInputRefs[0], $data['input_ref']);
            $this->assertEquals(1, sizeof($data['points']));
            $this->assertCompactPointMatchesFeature($data['points'][0], $feature, $data['pa_map']);
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
                'oldest'
            ],
            'links' => [
                'self',
                'first',
                'prev',
                'next',
                'last'
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
}
