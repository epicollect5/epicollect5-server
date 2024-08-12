<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal\Required;

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

class RequiredFormGroupTest extends TestCase
{
    use DatabaseTransactions, Assertions;

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

    public function test_form_group_required_text_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_integer_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.integer')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No integer question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_decimal_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.decimal')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No decimal question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_phone_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.phone')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No phone question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_date_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No date question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_time_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No time question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_textbox_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.textarea')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No textarea question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_radio_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.radio')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No radio question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_dropdown_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.dropdown')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No dropdown question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_checkbox_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.checkbox')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No checkbox question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_searchsingle_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.searchsingle')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break 2;
                    }
                }

                //inject searchsingle question if missing
                $searchsingleInput = ProjectDefinitionGenerator::createSearchSingleInput($input['ref']);
                $searchsingleInput['is_required'] = true;
                $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                $_group =& $_inputs[$inputIndex]['group'];
                $_group[] = $searchsingleInput;
                $groupInputRef = $searchsingleInput['ref'];
                //override entry generator with new project definition
                $this->entryGenerator = new EntryGenerator($this->projectDefinition);
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


        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_searchmultiple_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.searchmultiple')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
                //inject searchmultiple question if missing
                $searchmultipleInput = ProjectDefinitionGenerator::createSearchMultipleInput($input['ref']);
                $searchmultipleInput['is_required'] = true;
                $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                $_group =& $_inputs[$inputIndex]['group'];
                $_group[] = $searchmultipleInput;
                $groupInputRef = $searchmultipleInput['ref'];
                //override entry generator with new project definition
                $this->entryGenerator = new EntryGenerator($this->projectDefinition);
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    public function test_form_group_required_barcode_by_web_upload()
    {
        //get project definition
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first test question of the first group and add form uniqueness
        $groupInputRef = '';
        $inputAnswer = [];
        foreach ($inputs as $inputIndex => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInputIndex => $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.barcode')) {
                        $_inputs =& $this->projectDefinition['data']['project']['forms'][0]['inputs'];
                        $_group =& $_inputs[$inputIndex]['group'][$groupInputIndex];
                        $_group['is_required'] = true;
                        $groupInputRef = $groupInput['ref'];
                        break;
                    }
                }
            }
        }

        if (empty($groupInputRef)) {
            throw new Exception('No barcode question found in group');
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];

        $this->setRequiredAnswerAsEmpty(
            $payloadAnswers,
            $payload,
            $groupInputRef,
            $inputAnswer
        );

        $response = [];
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $groupInputRef
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            echo print_r($this->projectDefinition, true);
            $this->logTestError($e, $response);
        }
    }

    private function setRequiredAnswerAsEmpty($payloadAnswers, &$payload, $groupInputRef, $inputAnswer)
    {
        foreach ($payloadAnswers as $ref => $payloadAnswer) {
            if ($ref === $groupInputRef) {
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