<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PublicRoutes\Uniqueness\Form;

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

class UniquenessFormTest extends TestCase
{
    use DatabaseTransactions, Assertions;

    private $endpoint = 'api/upload/';

    public function setUp(): void
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
        $this->deviceId = Common::generateRandomHex();

    }

    public function test_form_uniqueness_text_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question and add form uniqueness
        $inputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_integer_by_web_upload()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_decimal_by_web_upload()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_phone_by_web_upload()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_date_by_web_upload_format_0()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_date_by_web_upload_format_1()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_date_by_web_upload_format_2()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_date_by_web_upload_format_3()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_date_by_web_upload_format_4()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_time_by_web_upload_format_0()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_time_by_web_upload_format_1()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_time_by_web_upload_format_2()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_time_by_web_upload_format_3()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_time_by_web_upload_format_4()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_textarea_by_web_upload()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    public function test_form_uniqueness_barcode_by_web_upload()
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
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $inputRef, $inputAnswer);

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

    private function setDuplicatedAnswer($payloadAnswers, &$payload, $inputRef, $inputAnswer)
    {
        foreach ($payloadAnswers as $ref => $payloadAnswer) {
            if ($ref === $inputRef) {
                $payload['data']['entry']['answers'][$inputRef] = $inputAnswer;
            } else {
                //clean all the other answer to avoid other duplicates
                if (is_array($payload['data']['entry']['answers'][$ref]['answer'])) {
                    //set to empty location
                    if (isset($payload['data']['entry']['answers'][$ref]['answer']['latitude'])) {
                        $payload['data']['entry']['answers'][$ref]['answer'] = [
                            'latitude' => '',
                            'longitude' => '',
                            'accuracy' => ''
                        ];
                    } else {
                        //multiple choice question, set to []
                        $payload['data']['entry']['answers'][$ref]['answer'] = [];
                    }
                } else {
                    //set to empty string
                    $payload['data']['entry']['answers'][$ref]['answer'] = '';
                }
            }
        }
    }

}