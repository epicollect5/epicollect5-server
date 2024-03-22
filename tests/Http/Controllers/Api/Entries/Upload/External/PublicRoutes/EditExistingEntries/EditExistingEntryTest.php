<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PublicRoutes\EditExistingEntries;

use Auth;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Project\ProjectExtraService;
use ec5\Traits\Assertions;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class EditExistingEntryTest extends TestCase
{
    use DatabaseTransactions, Assertions;

    private $endpoint = 'api/upload/';

    public function setUp()
    {
        parent::setUp();
        //remove leftovers
        User::where(
            'email',
            'like',
            '%example.net%')
            ->delete();

        $this->faker = Faker::create();

        //create fake user for testing
        $user = factory(User::class)->create();
        $role = config('epicollect.strings.project_roles.creator');

        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);

        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.public')
            ]
        );
        //add role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //create project structures
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($projectDefinition['data']);
        $projectMappingService = new ProjectMappingService();
        $projectMapping = [$projectMappingService->createEC5AUTOMapping($projectExtra)];


        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data']),
                'project_extra' => json_encode($projectExtra),
                'project_mapping' => json_encode($projectMapping)
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        $this->entryGenerator = new EntryGenerator($projectDefinition);
        $this->user = $user;
        $this->role = $role;
        $this->project = $project;
        $this->projectDefinition = $projectDefinition;
        $this->projectExtra = $projectExtra;
    }

    public function test_edit_existing_entry_text_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question
        $inputRef = '';
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $inputRef = $input['ref'];
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
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

        dd($entryFromDB);

        //try to upload payload text answer edited (reversing the string)
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = [
                    'answer' => strrev($existingAnswer['answer']),
                    'was_jumped' => false
                ];
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson([
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_text_by_app_upload_same_device()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question
        $inputRef = '';
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $inputRef = $input['ref'];
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        $deviceId = Common::generateRandomHex();
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $deviceId);
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

        //try to upload payload text answer edited (reversing the string)
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = [
                    'answer' => strrev($existingAnswer['answer']),
                    'was_jumped' => false
                ];
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            //perform an app upload without the user but with the same device ID
            Auth::guard('api_external')->login($this->user);
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson([
                    "data" =>
                        [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedEntryFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }


    public function test_edit_existing_entry_integer_by_app_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.integer')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $inputRef = $input['ref'];
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_decimal_by_app_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.decimal')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $inputRef = $input['ref'];
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_phone_by_app_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.phone')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $inputRef = $input['ref'];
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_date_by_app_upload_format_0()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[0]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.date')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];
                //
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_date_by_app_upload_format_1()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));

        $format = $formats[$indexedKeys[1]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.date')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];
                //  
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_date_by_app_upload_format_2()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));

        $format = $formats[$indexedKeys[2]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.date')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];
                //   
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_date_by_app_upload_format_3()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));

        $format = $formats[$indexedKeys[3]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.date')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];
                //   
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_date_by_app_upload_format_4()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));

        $format = $formats[$indexedKeys[4]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.date')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];
                //    
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_time_by_app_upload_format_0()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);

        $format = $formats[$reversedKeys[0]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];

                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_time_by_app_upload_format_1()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);

        $format = $formats[$reversedKeys[1]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];

                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_time_by_app_upload_format_2()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);

        $format = $formats[$reversedKeys[2]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];

                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_time_by_app_upload_format_3()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);

        $format = $formats[$reversedKeys[3]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];

                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_time_by_app_upload_format_4()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);

        $format = $formats[$reversedKeys[4]];

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['format'] = $format;
                $inputRef = $input['ref'];

                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);


        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_textarea_by_app_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first textarea question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.textarea')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $inputRef = $input['ref'];
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_barcode_by_app_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.barcode')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['uniqueness'] = 'form';
                $inputRef = $input['ref'];
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        //create entry
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

        //try to upload payload with the same text answer
        $existingAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    private function setEditedAnswer($payloadAnswers, &$payload, $inputRef, $editedInputAnswer)
    {
        foreach ($payloadAnswers as $ref => $payloadAnswer) {
            if ($ref === $inputRef) {
                $payload['data']['entry']['answers'][$inputRef] = $editedInputAnswer;
                break;
            }
        }
    }

}