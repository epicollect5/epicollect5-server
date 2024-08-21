<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal\Required;

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

class RequiredFormTest extends TestCase
{
    use DatabaseTransactions, Assertions;

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

    public function test_form_required_text_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_integer_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.integer')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_decimal_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.decimal')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_phone_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.phone')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_date_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.date')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_time_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_dropdown_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.dropdown')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_radio_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.radio')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_checkbox_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.checkbox')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_searchsingle_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.searchsingle')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
                $inputRef = $input['ref'];
                break;
            }
        }

        //update project in db
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);
        //  dd(json_encode($projectExtra));
        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
        ]);

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

        $response = [];
        //   dd(json_encode($payload));
        try {
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/web-upload/' . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_21",
                                "title" => "Required field is missing.",
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_searchmultiple_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.searchmultiple')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_textbox_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.textarea')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_form_required_barcode_by_web_upload()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question and set it as required
        $inputRef = '';


        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.barcode')) {
                $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['is_required'] = true;
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

        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $payload = $this->entryGenerator->createParentEntryPayload($formRef);
        $payloadAnswers = $payload['data']['entry']['answers'];
        $this->setRequiredAnswerAsEmpty($payloadAnswers, $payload, $inputRef);

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
                                "source" => $inputRef
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    private function setRequiredAnswerAsEmpty($payloadAnswers, &$payload, $inputRef)
    {
        foreach ($payloadAnswers as $ref => $payloadAnswer) {
            if ($ref === $inputRef) {
                //clean based on the question type
                if (is_array($payload['data']['entry']['answers'][$ref]['answer'])) {
                    //set to empty location
                    if (isset($payload['data']['entry']['answers'][$ref]['answer']['latitude'])) {
                        $payload['data']['entry']['answers'][$ref]['answer'] = [
                            'latitude' => '',
                            'longitude' => '',
                            'accuracy' => ''
                        ];
                    } else {
                        //multiple choice question with array, set to []
                        $payload['data']['entry']['answers'][$ref]['answer'] = [];
                    }
                } else {
                    //default to empty string
                    $payload['data']['entry']['answers'][$ref]['answer'] = '';
                }
            }
        }
    }
}