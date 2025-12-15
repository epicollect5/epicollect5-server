<?php

namespace Tests\Http\Controllers\Api\Entries\View\External\ExportRoutes;

use ec5\DTO\ProjectMappingDTO;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\ProjectStructure;
use ec5\Traits\Assertions;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Http\Controllers\Api\Entries\View\ViewEntriesBaseControllerTest;
use League\Csv\Reader;
use Throwable;

class EntriesExportCustomMappingTest extends ViewEntriesBaseControllerTest
{
    use DatabaseTransactions;
    use Assertions;

    private string $endpoint = 'api/export/entries/';

    /**
     * @throws Throwable
     */
    public function test_entries_export_endpoint_parent_single_entry_custom_mapping_not_modified_csv()
    {
        //set project as public so the endpoint is accessible without auth
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();

        $projectMapping = new ProjectMappingDTO();
        $payload = [
            'name' => 'Custom Mapping',
            'is_default' => false
        ];
        //generate custom mapping base on payload
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $mapping = json_decode($projectStructure->project_mapping, true);
        $projectMapping->setEC5AUTOMapping($mapping[0]);
        $projectMapping->createCustomMapping($payload);

        //update project structures
        $projectStructure->update([
            'project_mapping' => json_encode($projectMapping->getData())
        ]);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();

        //assert response passing parent form ref and the map index of 1, the custom mapping
        $queryString = '?form_ref=' . $formRef;
        $queryString .= '&map_index=1';
        $queryString .= '&format=csv';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get($this->endpoint . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            $csvContent = $response[0]->getContent();
            $reader = Reader::createFromString($csvContent);
            $reader->setHeaderOffset(0); // Assume the first row contains the headers

            // Get the CSV data as an associative array with header values as keys
            $records = $reader->getRecords(); // Each record is an associative array with headers as keys

            // Initialize an empty array to store the transformed data
            $entries = [];

            // Iterate over each record
            foreach ($records as $record) {
                // Initialize an empty array for the transformed record

                // Iterate over each header-value pair in the record
                // Use the header as the key and assign the corresponding value
                $transformedRecord = array_map(function ($value) {
                    return $value;
                }, $record);

                // Add the transformed record to the CSV data array
                $entries[] = $transformedRecord;
            }
            $response = [];
            $response[] = [
                'data' => [
                    'id' => $this->project->slug,
                    'type' => 'entries',
                    'entries' => $entries,
                    'mapping' => [
                        'map_name' => 'Custom Mapping',
                        'map_index' => 1
                    ]
                ]
            ];

            $mapping = json_decode($projectStructure->project_mapping, true);
            $forms = array_get($this->projectDefinition, 'data.project.forms');

            $inputsFlattened = $this->dataMappingService->getInputsFlattened($forms, $formRef);
            // dd($inputsFlattened);
            $onlyMapTheseRefs = array_map(function ($input) {
                return $input['ref'];
            }, $inputsFlattened);

            $this->assertEntriesExportResponseCSV(
                $response[0],
                $mapping,
                [
                    'form_ref' => $formRef,
                    'form_index' => 0,
                    'onlyMapTheseRefs' => $onlyMapTheseRefs,
                    'projectDefinition' => $this->projectDefinition
                ]
            );

            $entryFromResponse = $response[0]['data']['entries'][0];
            //dd($entryFromResponse);
            $this->assertEquals($entryFromDB->uuid, $entryFromResponse['ec5_uuid']);
            $this->assertEquals($entryFromDB->title, $entryFromResponse['title']);
            //timestamp
            $this->assertEquals(
                str_replace(' ', 'T', $entryFromDB->created_at) . '.000Z',
                $entryFromResponse['created_at']
            );
            $this->assertEquals(
                str_replace(' ', 'T', $entryFromDB->uploaded_at) . '.000Z',
                $entryFromResponse['uploaded_at']
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_entries_export_endpoint_parent_single_entry_custom_mapping_modified()
    {
        //set project as public so the endpoint is accessible without auth
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();

        $projectMapping = new ProjectMappingDTO();
        $payload = [
            'name' => 'Custom Mapping',
            'is_default' => false
        ];
        //generate custom mapping base on payload
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $mapping = json_decode($projectStructure->project_mapping, true);
        $projectMapping->setEC5AUTOMapping($mapping[0]);
        $projectMapping->createCustomMapping($payload);

        //edit the newly created custom mapping
        $customMapping = $projectMapping->getData()[1];
        $modifiedMapping = $this->getModifiedMapping($customMapping);

        //update DTO
        $projectMapping->updateMap(1, $modifiedMapping);
        //update project structures with the new mapping
        $projectStructure->update([
            'project_mapping' => json_encode($projectMapping->getData())
        ]);

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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();

        //assert response passing parent form ref and the map index of 1, the custom mapping
        $queryString = '?form_ref=' . $formRef;
        $queryString .= '&map_index=1';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get($this->endpoint . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            $mapping = json_decode($projectStructure->project_mapping, true);
            $forms = array_get($this->projectDefinition, 'data.project.forms');

            $inputsFlattened = $this->dataMappingService->getInputsFlattened($forms, $formRef);
            // dd($inputsFlattened);
            $onlyMapTheseRefs = array_map(function ($input) {
                return $input['ref'];
            }, $inputsFlattened);

            $this->assertEntriesExportResponse(
                $response[0],
                $mapping,
                [
                    'form_ref' => $formRef,
                    'form_index' => 0,
                    'onlyMapTheseRefs' => $onlyMapTheseRefs,
                    'projectDefinition' => $this->projectDefinition
                ],
                1
            );

            $json = json_decode($response[0]->getContent(), true);
            $entryFromResponse = $json['data']['entries'][0];
            //dd($entryFromResponse);
            $this->assertEquals($entryFromDB->uuid, $entryFromResponse['ec5_uuid']);
            $this->assertEquals($entryFromDB->title, $entryFromResponse['title']);
            //timestamp
            $this->assertEquals(
                str_replace(' ', 'T', $entryFromDB->created_at) . '.000Z',
                $entryFromResponse['created_at']
            );
            $this->assertEquals(
                str_replace(' ', 'T', $entryFromDB->uploaded_at) . '.000Z',
                $entryFromResponse['uploaded_at']
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_entries_export_endpoint_parent_multiple_entries_custom_mapping_modified()
    {
        //set project as public so the endpoint is accessible without auth
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();

        $projectMapping = new ProjectMappingDTO();
        $payload = [
            'name' => 'Custom Mapping',
            'is_default' => false
        ];
        //generate custom mapping base on payload
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $mapping = json_decode($projectStructure->project_mapping, true);
        $projectMapping->setEC5AUTOMapping($mapping[0]);
        $projectMapping->createCustomMapping($payload);

        //edit the newly created custom mapping
        $customMapping = $projectMapping->getData()[1];
        $modifiedMapping = $this->getModifiedMapping($customMapping);

        //update DTO
        $projectMapping->updateMap(1, $modifiedMapping);
        //update project structures with the new mapping
        $projectStructure->update([
            'project_mapping' => json_encode($projectMapping->getData())
        ]);

        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        $numOfEntries = rand(5, 10);
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

        //assert row is created
        $this->assertCount(
            $numOfEntries,
            Entry::where('project_id', $this->project->id)->get()
        );

        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        //assert response passing parent form ref
        $queryString = '?form_ref=' . $formRef;
        $queryString .= '&map_index=1';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get($this->endpoint . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            $mapping = json_decode($projectStructure->project_mapping, true);
            $forms = array_get($this->projectDefinition, 'data.project.forms');

            $inputsFlattened = $this->dataMappingService->getInputsFlattened($forms, $formRef);
            $onlyMapTheseRefs = array_map(function ($input) {
                return $input['ref'];
            }, $inputsFlattened);

            $this->assertEntriesExportResponse(
                $response[0],
                $mapping,
                [
                    'form_ref' => $formRef,
                    'form_index' => 0,
                    'onlyMapTheseRefs' => $onlyMapTheseRefs,
                    'projectDefinition' => $this->projectDefinition
                ],
                1
            );

            $json = json_decode($response[0]->getContent(), true);
            $this->assertCount($numOfEntries, $json['data']['entries']);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')]
    public function test_entries_export_endpoint_child_single_entry_custom_mapping_modified()
    {
        //set project as public so the endpoint is accessible without auth
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();

        $projectMapping = new ProjectMappingDTO();
        $payload = [
            'name' => 'Custom Mapping',
            'is_default' => false
        ];

        //generate custom mapping base on payload
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $mapping = json_decode($projectStructure->project_mapping, true);
        $projectMapping->setEC5AUTOMapping($mapping[0]);
        $projectMapping->createCustomMapping($payload);

        //edit the newly created custom mapping
        $customMapping = $projectMapping->getData()[1];
        $modifiedMapping = $this->getModifiedMapping($customMapping);

        //update DTO
        $projectMapping->updateMap(1, $modifiedMapping);
        //update project structures with the new mapping
        $projectStructure->update([
            'project_mapping' => json_encode($projectMapping->getData())
        ]);


        //generate a parent entry (form 0)
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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );
        $parentEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a child entry for this parent
        $childFormRef = array_get($this->projectDefinition, 'data.project.forms.1.ref');

        if (is_null($childFormRef)) {
            throw new Exception('This project does not have a child form with index 1');
        }

        $childEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $childEntryPayloads[$i] = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
            $entryRowBundle = $this->entryGenerator->createChildEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $childEntryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $childEntryPayloads[$i]
            );
        }

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->get()
        );

        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //assert response passing the child form ref and map_index 1
        $queryString = '?form_ref=' . $childFormRef;
        $queryString .= '&map_index=1';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get($this->endpoint . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            $mapping = json_decode($projectStructure->project_mapping, true);
            $forms = array_get($this->projectDefinition, 'data.project.forms');

            $inputsFlattened = $this->dataMappingService->getInputsFlattened($forms, $childFormRef);
            $onlyMapTheseRefs = array_map(function ($input) {
                return $input['ref'];
            }, $inputsFlattened);


            $this->assertEntriesExportResponse(
                $response[0],
                $mapping,
                [
                    'form_ref' => $childFormRef,
                    'form_index' => 1,
                    'onlyMapTheseRefs' => $onlyMapTheseRefs,
                    'projectDefinition' => $this->projectDefinition
                ],
                1
            );

            $json = json_decode($response[0]->getContent(), true);
            $entryFromResponse = $json['data']['entries'][0];
            $this->assertEquals($childEntryFromDB->uuid, $entryFromResponse['ec5_uuid']);
            $this->assertEquals($parentEntryFromDB->uuid, $entryFromResponse['ec5_parent_uuid']);
            $this->assertEquals($childEntryFromDB->title, $entryFromResponse['title']);
            //timestamp
            $this->assertEquals(
                str_replace(' ', 'T', $childEntryFromDB->created_at) . '.000Z',
                $entryFromResponse['created_at']
            );
            $this->assertEquals(
                str_replace(' ', 'T', $childEntryFromDB->uploaded_at) . '.000Z',
                $entryFromResponse['uploaded_at']
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('multipleRunProvider')]
    public function test_entries_export_endpoint_branch_of_form_0_single_entry_custom_mapping_modified()
    {
        //set project as public so the endpoint is accessible without auth
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();

        $projectMapping = new ProjectMappingDTO();
        $payload = [
            'name' => 'Custom Mapping',
            'is_default' => false
        ];

        //generate custom mapping base on payload
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $mapping = json_decode($projectStructure->project_mapping, true);
        $projectMapping->setEC5AUTOMapping($mapping[0]);
        $projectMapping->createCustomMapping($payload);

        //edit the newly created custom mapping
        $customMapping = $projectMapping->getData()[1];
        $modifiedMapping = $this->getModifiedMapping($customMapping);

        //update DTO
        $projectMapping->updateMap(1, $modifiedMapping);
        //update project structures with the new mapping
        $projectStructure->update([
            'project_mapping' => json_encode($projectMapping->getData())
        ]);

        //generate a parent entry (form 0)
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

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );
        $parentEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];

        $branches = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branches[] = $input;
            }
        }

        //generate a branch entry for the first branch (index 0)
        $randomBranchIndex = $this->faker->randomKey($branches);
        $branchRef = $branches[$randomBranchIndex]['ref'];
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branches[$randomBranchIndex]['branch'],
                $parentEntryFromDB->uuid,
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

        //assert row is created
        $this->assertCount(
            1,
            BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->get()
        );
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //assert response passing the form ref and the branch ref
        $queryString = '?form_ref=' . $formRef . '&branch_ref=' . $branchRef;
        $queryString .= '&map_index=1';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/export/entries/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $mapping = json_decode($projectStructure->project_mapping, true);
            $forms = array_get($this->projectDefinition, 'data.project.forms');

            $inputsFlattened = $this->dataMappingService->getBranchInputsFlattened($forms, $formRef, $branchRef);

            $onlyMapTheseRefs = array_map(function ($input) {
                return $input['ref'];
            }, $inputsFlattened);

            $this->assertEntriesExportResponse(
                $response[0],
                $mapping,
                [
                    'form_ref' => $formRef,
                    'form_index' => 0,
                    'onlyMapTheseRefs' => $onlyMapTheseRefs,
                    'projectDefinition' => $this->projectDefinition,
                    'branchRef' => $branchRef
                ],
                1
            );

            $json = json_decode($response[0]->getContent(), true);
            $entryFromResponse = $json['data']['entries'][0];
            //dd($entryFromResponse);
            $this->assertEquals($branchEntryFromDB->uuid, $entryFromResponse['ec5_branch_uuid']);
            $this->assertEquals($branchEntryFromDB->owner_uuid, $entryFromResponse['ec5_branch_owner_uuid']);
            $this->assertEquals($branchEntryFromDB->title, $entryFromResponse['title']);
            //timestamp
            $this->assertEquals(
                str_replace(' ', 'T', $branchEntryFromDB->created_at) . '.000Z',
                $entryFromResponse['created_at']
            );
            $this->assertEquals(
                str_replace(' ', 'T', $branchEntryFromDB->uploaded_at) . '.000Z',
                $entryFromResponse['uploaded_at']
            );

            //assert branch owner row ID
            $ownerEntry = Entry::where('uuid', $branchEntryFromDB->owner_uuid)->first();
            $this->assertEquals($branchEntryFromDB->owner_entry_id, $ownerEntry->id);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }
}
