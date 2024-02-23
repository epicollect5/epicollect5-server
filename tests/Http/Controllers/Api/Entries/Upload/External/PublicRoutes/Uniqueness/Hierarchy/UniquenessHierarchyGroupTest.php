<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PublicRoutes\Uniqueness\Hierarchy;

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

class UniquenessHierarchyGroupTest extends TestCase
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
        $projectDefinition = ProjectDefinitionGenerator::createProject(5);

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

    public function test_hierarchy_group_uniqueness_text_by_web_upload()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');
        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo(print_r($payload, true));
            echo "\n";
            echo "\n";
            echo(print_r($existingAnswers, true));
            echo "\n";
            echo "\n";
            echo $groupInputRef;
            echo "\n";
            echo "\n";
            echo print_r($groupInputAnswer, true);
            echo "\n";
            echo "\n";
            echo $childEntryFromDB->parent_uuid;
            echo "\n";
            echo "\n";
            echo $childEntryFromDB->parent_form_ref;
            echo "\n";
            echo "\n";
            //echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_integer_by_web_upload()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');
        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.integer')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_decimal_by_web_upload()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');
        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.decimal')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);

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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_phone_by_web_upload()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');
        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.phone')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_date_by_web_upload_format_0()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[0]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_date_by_web_upload_format_1()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[1]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_date_by_web_upload_format_2()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[2]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_date_by_web_upload_format_3()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[3]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_date_by_web_upload_format_4()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        $format = $formats[$indexedKeys[4]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_time_by_web_upload_format_0()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[0]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_time_by_web_upload_format_1()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[1]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_time_by_web_upload_format_2()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[2]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_time_by_web_upload_format_3()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[2]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_time_by_web_upload_format_4()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');

        // Convert associative array keys into indexed array keys
        $formats = config('epicollect.strings.datetime_format');
        $indexedKeys = array_values(array_keys($formats));
        // Reverse the array
        $reversedKeys = array_reverse($indexedKeys);
        $format = $formats[$reversedKeys[4]];

        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_textbox_by_web_upload()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');
        //get the first question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.textarea')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_hierarchy_group_uniqueness_barcode_by_web_upload()
    {
        //get child form inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');
        //get the first barcode question for the first group of the child form and add hierarchy uniqueness
        $groupInputRef = '';
        $groupInputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.barcode')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][1]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['uniqueness'] = 'hierarchy';
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


        //create parent entry
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
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //try to upload payload (child entry) with the same text answer
        $existingAnswers = json_decode($childEntryFromDB->entry_data, true)['entry']['answers'];
        foreach ($existingAnswers as $ref => $existingAnswer) {
            if ($ref === $groupInputRef) {
                $groupInputAnswer = $existingAnswer;
            }
        }

        $payload = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setDuplicatedAnswer($payloadAnswers, $payload, $groupInputRef, $groupInputAnswer);


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
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @param $payloadAnswers
     * @param $payload
     * @param $groupInputRef
     * @param $inputAnswer
     * @return void
     *
     * Set a duplicated answer in the payload only for the answer we are testing
     *
     * All the other answers to empty to isolate against other errors
     */
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