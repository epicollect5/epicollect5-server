<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal\Uniqueness\Form;

use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Entries\BranchEntry;
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

class UniquenessFormBranchGroupTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

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
        $projectDefinition = ProjectDefinitionGenerator::createProject(5);

        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private')
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

    public function test_form_uniqueness_branch_group_text_by_web_upload()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.text')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_integer_by_web_upload()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.integer')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_decimal_by_web_upload()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.decimal')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_phone_by_web_upload()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.phone')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_date_by_web_upload_format_0()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[0]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_date_by_web_upload_format_1()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[1]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_date_by_web_upload_format_2()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[2]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_date_by_web_upload_format_3()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[3]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_date_by_web_upload_format_4()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[4]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_time_by_web_upload_format_0()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[0]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_time_by_web_upload_format_1()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[1]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_time_by_web_upload_format_2()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[2]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_time_by_web_upload_format_3()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[3]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_time_by_web_upload_format_4()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[4]];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['datetime_format'] = $format;

                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_textbox_by_web_upload()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.textarea')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_uniqueness_branch_group_barcode_by_web_upload()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        //get the first text question of the first group in the branch and add form uniqueness
        $branchGroupInputRef = '';
        $ownerInputRef = '';
        $inputAnswer = [];
        $branchInputs = [];

        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {

                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInputIndex => $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.barcode')) {
                                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['group'][$groupInputIndex]['uniqueness'] = 'form';
                                $branchGroupInputRef = $groupInput['ref'];
                                break 3;
                            }
                        }
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


        //create owner entry
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

        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $ownerEntryFromDB->uuid,
                $ownerInputRef
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
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (branch entry) with the same text answer
        $existingAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $branchGroupInputRef) {
                $inputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $ownerEntryFromDB->uuid,
            $ownerInputRef
        );
        $payloadAnswers = $payload['data']['branch_entry']['answers'];


        $this->setDuplicatedAnswer($payloadAnswers, $payload, $branchGroupInputRef, $inputAnswer);

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_22",
                                "title" => "Answer is not unique.",
                                "source" => $branchGroupInputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @param $payloadAnswers
     * @param $payload
     * @param $inputRef
     * @param $inputAnswer
     * @return void
     *
     * Set a duplicated answer in the payload only for the answer we are testing
     *
     * All the other answers to empty to isolate against other errors
     */
    private function setDuplicatedAnswer($payloadAnswers, &$payload, $inputRef, $inputAnswer)
    {
        foreach ($payloadAnswers as $ref => $payloadAnswer) {
            if ($ref === $inputRef) {
                $payload['data']['branch_entry']['answers'][$inputRef] = $inputAnswer;
            } else {
                //clean all the other answer to avoid other duplicates
                if (is_array($payload['data']['branch_entry']['answers'][$ref]['answer'])) {
                    //set to empty location
                    if (isset($payload['data']['branch_entry']['answers'][$ref]['answer']['latitude'])) {
                        $payload['data']['branch_entry']['answers'][$ref]['answer'] = [
                            'latitude' => '',
                            'longitude' => '',
                            'accuracy' => ''
                        ];
                    } else {
                        //multiple choice question, set to []
                        $payload['data']['branch_entry']['answers'][$ref]['answer'] = [];
                    }
                } else {
                    //set to empty string
                    $payload['data']['branch_entry']['answers'][$ref]['answer'] = '';
                }
            }
        }
    }
}
