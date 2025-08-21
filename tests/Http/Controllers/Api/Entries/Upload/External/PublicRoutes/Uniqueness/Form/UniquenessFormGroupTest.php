<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PublicRoutes\Uniqueness\Form;

use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
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
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UniquenessFormGroupTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private string $endpoint = 'api/upload/';

    public function setUp(): void
    {
        parent::setUp();
        //remove leftovers
        User::where(
            'email',
            'like',
            '%example.net%'
        )
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

    public function test_form_group_uniqueness_text_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No text question found in group');
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_integer_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.integer')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];


        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_decimal_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.decimal')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_phone_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.phone')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_date_by_web_upload_format_0()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[0]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_date_by_web_upload_format_1()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[1]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_date_by_web_upload_format_2()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[2]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_date_by_web_upload_format_3()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[3]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_date_by_web_upload_format_4()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[4]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_time_by_web_upload_format_0()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[0]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_time_by_web_upload_format_1()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[1]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_time_by_web_upload_format_2()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[2]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_time_by_web_upload_format_3()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[3]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_time_by_web_upload_format_4()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[4]];

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $_group['datetime_format']
                            = $format;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_textarea_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.textarea')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_uniqueness_barcode_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.barcode')) {
                        $_inputs = & $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group = & $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'form';
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
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
            if ($ref === $groupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    private function setDuplicatedAnswer($payloadAnswers, &$payload, $groupInputRef, $inputAnswer)
    {
        foreach ($payloadAnswers as $ref => $payloadAnswer) {
            if ($ref === $groupInputRef) {
                $payload['data']['entry']['answers'][$groupInputRef] = $inputAnswer;
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
