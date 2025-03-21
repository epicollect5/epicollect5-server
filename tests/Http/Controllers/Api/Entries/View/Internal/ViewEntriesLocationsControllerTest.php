<?php

namespace Tests\Http\Controllers\Api\Entries\View\Internal;

use Auth;
use ec5\Libraries\Utilities\Common;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\ProjectRole;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Http\Controllers\Api\Entries\View\ViewEntriesBaseControllerTest;
use Throwable;

class ViewEntriesLocationsControllerTest extends ViewEntriesBaseControllerTest
{
    use DatabaseTransactions;
    use Assertions;

    /**
     * @throws Throwable
     */
    public function test_parent_entry_row_stored_to_db()
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
    }

    /**
     * @throws Throwable
     */
    public function test_multiple_parent_entry_rows_stored_to_db()
    {
        $count = rand(50, 100);
        for ($i = 0; $i < $count; $i++) {
            $this->test_parent_entry_row_stored_to_db();
        }
    }


    /**
     * @throws Throwable
     */
    public function test_location_endpoint_input_ref_missing()
    {
        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        //assert response missing the input_ref
        $queryString = '?form_ref=' . $formRef;
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(400);
            $response[0]->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_243",
                        "title" => "Invalid input ref",
                        "source" => "rule-query-string"
                    ]
                ]
            ]);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_location_endpoint_input_ref_not_existing()
    {
        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        //assert response with a random input_ref
        $inputRef = Generators::inputRef($formRef);
        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $inputRef;
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(400);
            $response[0]->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_84",
                        "title" => "Question does not exist.",
                        "source" => $inputRef
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_location_endpoint_form_ref_not_existing()
    {
        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        //assert response passing the location input_ref and first form ref
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);
        $randomFormRef = Generators::formRef($this->project->ref);
        $queryString = '?form_ref=' . $randomFormRef . '&input_ref=' . $locationInputRefs[0];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(400);
            $response[0]->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_15",
                        "title" => "Form does not exist.",
                        "source" => $randomFormRef
                    ]
                ]
            ]);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_entries_locations_endpoint_default_to_first_form()
    {
        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entriesSavedUuids = Entry::where('project_id', $this->project->id)->pluck('uuid')->toArray();
        $entriesSavedTitles = Entry::where('project_id', $this->project->id)->pluck('title')->toArray();

        //get location inputs for the parent form (only one for this test)
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);

        //assert response passing the location input_ref and first form ref
        $queryString = '?input_ref=' . $locationInputRefs[0];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            $this->assertEquals(1, sizeof($geoJson['features']));
            $this->assertEquals($entriesSavedUuids[0], $geoJson['features'][0]['id']);
            $this->assertEquals($entriesSavedTitles[0], $geoJson['features'][0]['properties']['title']);

            $this->assertGeoJsonData(
                $locationInputRefs[0],
                $entryPayloads[0]['data']['entry'],
                $geoJson['features'][0]
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_entries_locations_endpoint_form_0_multiple_entries()
    {
        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');

        $numOfEntries = rand(5, 10);
        $entryPayloads = [];
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            $numOfEntries,
            Entry::where('project_id', $this->project->id)->get()
        );

        $entriesSavedUuids = Entry::where('project_id', $this->project->id)->pluck('uuid')->toArray();
        $entriesSavedTitles = Entry::where('project_id', $this->project->id)->pluck('title')->toArray();


        //get location inputs for the parent form
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);
        $requestedInputRef = $this->faker->randomElement($locationInputRefs);

        //assert response passing a random location input_ref  and first form ref
        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $requestedInputRef;
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            $this->assertEquals($numOfEntries, sizeof($geoJson['features']));
            foreach ($geoJson['features'] as $feature) {
                $this->assertContains($feature['id'], $entriesSavedUuids);
                $this->assertContains($feature['properties']['uuid'], $entriesSavedUuids);
                $this->assertContains($feature['properties']['title'], $entriesSavedTitles);
            }
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_entries_locations_endpoint_form_0_single_entry()
    {
        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entriesSavedUuids = Entry::where('project_id', $this->project->id)->pluck('uuid')->toArray();
        $entriesSavedTitles = Entry::where('project_id', $this->project->id)->pluck('title')->toArray();


        //get location inputs for the parent form (only one for this test)
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);

        //assert response passing the location input_ref and first form ref
        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $locationInputRefs[0];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            $this->assertEquals(1, sizeof($geoJson['features']));
            $this->assertEquals($entriesSavedUuids[0], $geoJson['features'][0]['id']);
            $this->assertEquals($entriesSavedUuids[0], $geoJson['features'][0]['properties']['uuid']);
            $this->assertEquals($entriesSavedTitles[0], $geoJson['features'][0]['properties']['title']);

            //Uploaded: 2024-02-07T15:56:10.000Z
            //Actual: 2024-02-07
            $this->assertEquals(
                date('Y-m-d', strtotime($entryPayloads[0]['data']['entry']['created_at'])),
                $geoJson['features'][0]['properties']['created_at']
            );


            $this->assertGeoJsonData(
                $locationInputRefs[0],
                $entryPayloads[0]['data']['entry'],
                $geoJson['features'][0]
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_branch_entries_locations_endpoint_form_0_single_entry()
    {
        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first branch
        $branchRef = '';
        $branchIndex = 0;
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $branchIndex = $index;
                $branchRef = $input['ref'];
                break;
            }
        }

        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $inputs[$branchIndex]['branch'],
                $entryPayloads[0]['data']['id'],
                $branchRef
            );
            $entryRowBundle = $this->entryGenerator->createBranchEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $branchEntryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $branchEntryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $this->assertCount(
            1,
            BranchEntry::where(
                'owner_entry_id',
                Entry::where('uuid', $entryPayloads[0]['data']['id'])->pluck('id')
            )->get()
        );

        $branchEntriesSavedUuids = BranchEntry::where('project_id', $this->project->id)->pluck('uuid')->toArray();
        $branchEntriesSavedTitles = BranchEntry::where('project_id', $this->project->id)->pluck('title')->toArray();

        //get location inputs for the branch entry (only one for this test)
        $locationBranchInputRefs = Common::getBranchLocationInputRefs($this->projectDefinition, 0, $branchRef);

        //assert response passing the location input_ref and first form ref and the branch_ref
        //imp: input_ref is the branch input of type location, branch_ref is the branch owning that input
        $queryString = '?form_ref=' . $formRef . '&input_ref='.$locationBranchInputRefs[0].'&branch_ref=' .$branchRef ;
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            $this->assertEquals(1, sizeof($geoJson['features']));
            $this->assertEquals($branchEntriesSavedUuids[0], $geoJson['features'][0]['id']);
            $this->assertEquals($branchEntriesSavedUuids[0], $geoJson['features'][0]['properties']['uuid']);
            $this->assertEquals($branchEntriesSavedTitles[0], $geoJson['features'][0]['properties']['title']);

            //Uploaded: 2024-02-07T15:56:10.000Z
            //Actual: 2024-02-07
            $this->assertEquals(
                date('Y-m-d', strtotime($branchEntryPayloads[0]['data']['branch_entry']['created_at'])),
                $geoJson['features'][0]['properties']['created_at']
            );


            $this->assertGeoJsonData(
                $locationBranchInputRefs[0],
                $branchEntryPayloads[0]['data']['branch_entry'],
                $geoJson['features'][0]
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_creator_should_view_all_entries()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $numOfEntries = rand(5, 10);
        $entryPayloads = [];
        //creator entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //add manager to project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        //add manager entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $manager,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }


        //assert rows are created
        $this->assertCount(
            $numOfEntries * 2,
            Entry::where('project_id', $this->project->id)->get()
        );

        //get location inputs for the parent form
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);
        $requestedInputRef = $this->faker->randomElement($locationInputRefs);
        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $requestedInputRef;
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            //should be able to get all the entries ($this->user)
            $this->assertCount($numOfEntries * 2, $geoJson['features']);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_manager_should_view_all_entries()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $numOfEntries = rand(5, 10);
        $entryPayloads = [];
        //creator entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //add manager to project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        //add manager entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $manager,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }


        //assert rows are created
        $this->assertCount(
            $numOfEntries * 2,
            Entry::where('project_id', $this->project->id)->get()
        );

        //get location inputs for the parent form
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);
        $requestedInputRef = $this->faker->randomElement($locationInputRefs);
        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $requestedInputRef;
        $response = [];
        try {
            $response[] = $this->actingAs($manager)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            //should be able to get all the entries ($this->user)
            $this->assertCount($numOfEntries * 2, $geoJson['features']);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_curator_should_view_all_entries()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $numOfEntries = rand(5, 10);
        $entryPayloads = [];
        //creator entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //add curator to project
        $curator = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $curator->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.curator')
        ]);

        //add curator entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $curator,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }


        //assert rows are created
        $this->assertCount(
            $numOfEntries * 2,
            Entry::where('project_id', $this->project->id)->get()
        );

        //get location inputs for the parent form
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);
        $requestedInputRef = $this->faker->randomElement($locationInputRefs);
        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $requestedInputRef;
        $response = [];
        try {
            $response[] = $this->actingAs($curator)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            //should be able to get all the entries ($this->user)
            $this->assertCount($numOfEntries * 2, $geoJson['features']);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_viewer_should_view_all_entries()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $numOfEntries = rand(5, 10);
        $entryPayloads = [];
        //creator entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //add viewer to project
        $viewer = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $viewer->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.viewer')
        ]);

        //add viewer entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $viewer,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }


        //assert rows are created
        $this->assertCount(
            $numOfEntries * 2,
            Entry::where('project_id', $this->project->id)->get()
        );

        //get location inputs for the parent form
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);
        $requestedInputRef = $this->faker->randomElement($locationInputRefs);
        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $requestedInputRef;
        $response = [];
        try {
            $response[] = $this->actingAs($viewer)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            //should be able to get all the entries ($this->user)
            $this->assertCount($numOfEntries * 2, $geoJson['features']);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_collector_can_view_only_own_entries()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $numOfEntries = rand(5, 10);
        $entryPayloads = [];
        //creator entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            Auth::login($this->user);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );
            Auth::logout();

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }


        //add collector to project
        $collector = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        //add another collector to project
        $collector2 = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collector2->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        //add collector entries
        $collectorEntriesUuids = [];
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $collectorEntriesUuids[] = $entryPayloads[$i]['data']['id'];
            Auth::login($collector);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $collector,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );
            Auth::logout();

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //add collector2 entries
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            Auth::login($collector2);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $collector2,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );
            Auth::logout();

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }


        //assert rows are created
        $this->assertCount(
            $numOfEntries * 3,
            Entry::where('project_id', $this->project->id)->get()
        );

        //get location inputs for the parent form
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);
        $requestedInputRef = $this->faker->randomElement($locationInputRefs);
        $queryString = '?form_ref=' . $formRef . '&input_ref=' . $requestedInputRef . '&user_id=' . $collector->id;
        $response = [];
        try {
            $response[] = $this->actingAs($collector)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            //should be able to get only own entries
            $this->assertCount($numOfEntries, $geoJson['features']);

            //check entries uuids match
            foreach ($geoJson['features'] as $feature) {
                $this->assertTrue(in_array($feature['id'], $collectorEntriesUuids));
            }
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_entries_locations_endpoint_no_locations_found_because_null()
    {
        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        Entry::where('uuid', $entryPayloads[0]['data']['id'])->update([
            'geo_json_data' => null
        ]);


        //get location inputs for the parent form (only one for this test)
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);

        //assert response passing the location input_ref and first form ref
        $queryString = '?input_ref=' . $locationInputRefs[0];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJson = $json['data']['geojson'];

            //assert geojson features is empty
            $this->assertEquals(0, sizeof($geoJson['features']));
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_entries_locations_endpoint_no_locations_found_because_no_locations_for_question()
    {
        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        //get location inputs for the parent form (only one for this test)
        $locationInputRefs = Common::getLocationInputRefs($this->projectDefinition, 0);


        //modify the geojson in the database to remove any location data for that input ref
        $entry = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $geoJSONString =  $entry->geo_json_data;
        $geoJSON = json_decode($geoJSONString, true);
        unset($geoJSON[$locationInputRefs[0]]);
        $entry->geo_json_data = json_encode($geoJSON);
        $entry->save();

        //assert response passing the location input_ref and first form ref
        $queryString = '?input_ref=' . $locationInputRefs[0];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            $this->assertEntriesLocationsResponse($response[0]);

            $json = json_decode($response[0]->getContent(), true);
            $geoJSON = $json['data']['geojson'];

            //assert geojson features is empty
            $this->assertEquals(0, sizeof($geoJSON['features']));
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }


}
