<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal\EditExistingEntries;

use Auth;
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
use Throwable;

class EditExistingHierarchyEntryTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private string $endpoint = 'api/internal/web-upload/';

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
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_text_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload text answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_integer_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first integer question
        $inputRef = '';
        $inputInteger = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.integer')) {
                $inputRef = $input['ref'];
                $inputInteger = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload integer answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputInteger, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_decimal_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first decimal question
        $inputRef = '';
        $inputDecimal = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.decimal')) {
                $inputRef = $input['ref'];
                $inputDecimal = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload integer answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDecimal, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_phone_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first phone question
        $inputRef = '';
        $inputPhone = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.phone')) {
                $inputRef = $input['ref'];
                $inputPhone = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload phone answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputPhone, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_date_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first date question
        $inputRef = '';
        $inputDate = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.date')) {
                $inputRef = $input['ref'];
                $inputDate = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload date answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDate, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_time_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first time question
        $inputRef = '';
        $inputTime = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                $inputRef = $input['ref'];
                $inputTime = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload time answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTime, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_dropdown_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first dropdown question
        $inputRef = '';
        $inputDropdown = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                $inputRef = $input['ref'];
                $inputDropdown = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload dropdown answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDropdown, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_radio_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first radio question
        $inputRef = '';
        $inputRadio = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.radio')) {
                $inputRef = $input['ref'];
                $inputRadio = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload radio answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputRadio, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_checkbox_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first checkbox question
        $inputRef = '';
        $inputCheckbox = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.checkbox')) {
                $inputRef = $input['ref'];
                $inputCheckbox = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload checkbox answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputCheckbox, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_searchsingle_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first searchsingle question
        $inputRef = '';
        $inputSearchsingle = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.searchsingle')) {
                $inputRef = $input['ref'];
                $inputSearchsingle = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload searchsingle answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputSearchsingle, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_searchmultiple_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first searchsingle question
        $inputRef = '';
        $inputSearchmultiple = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.searchmultiple')) {
                $inputRef = $input['ref'];
                $inputSearchmultiple = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload searchmultiple answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputSearchmultiple, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_textbox_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first textbox question
        $inputRef = '';
        $inputTextbox = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.textarea')) {
                $inputRef = $input['ref'];
                $inputTextbox = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload textbox answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextbox, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_location_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first textbox question
        $inputRef = '';
        $inputLocation = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
                $inputRef = $input['ref'];
                $inputLocation = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload location answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputLocation, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_photo_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first photo question
        $inputRef = '';
        $inputPhoto = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.photo')) {
                $inputRef = $input['ref'];
                $inputPhoto = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload location answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputPhoto, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_audio_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first photo question
        $inputRef = '';
        $inputAudio = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.audio')) {
                $inputRef = $input['ref'];
                $inputAudio = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload audio answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputAudio, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_video_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first video question
        $inputRef = '';
        $inputVideo = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
                $inputRef = $input['ref'];
                $inputVideo = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload video answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputVideo, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_barcode_by_web_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first barcode question
        $inputRef = '';
        $inputBarcode = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.barcode')) {
                $inputRef = $input['ref'];
                $inputBarcode = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload video answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputBarcode, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_text_by_web_upload_another_user_with_manager_role()
    {
        //add a manager to the project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question
        $inputText = '';
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $inputText = $input;
                break;
            }
        }

        //create entry with the creator role
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload text answer edited (reversing the string)
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputText['ref']) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputText['ref'], $editedInputAnswer);

        $response = [];
        try {
            Auth::guard('api_internal')->login($manager);
            $response[] = $this->actingAs($manager)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
                if ($ref === $inputText['ref']) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches?????
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_text_by_web_upload_another_user_with_curator_role()
    {
        //add a curator to the project
        $curator = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $curator->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.curator')
        ]);

        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question
        $inputText = '';
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $inputText = $input;
                break;
            }
        }

        //create entry with the creator role
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //try to upload payload text answer edited (reversing the string)
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputText['ref']) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputText['ref'], $editedInputAnswer);

        $response = [];
        try {
            Auth::guard('api_internal')->login($curator);
            $response[] = $this->actingAs($curator)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
                if ($ref === $inputText['ref']) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches?????
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_text_by_web_upload_same_collector()
    {
        //add a collector to the project
        $collector = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question
        $inputText = '';
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $inputText = $input;
                break;
            }
        }

        //create entry with the creator role
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($collector);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $collector,
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
            if ($ref === $inputText['ref']) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputText['ref'], $editedInputAnswer);

        $response = [];
        try {
            Auth::guard('api_internal')->login($collector);
            $response[] = $this->actingAs($collector)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
                if ($ref === $inputText['ref']) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches?????
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_text_by_web_upload_different_collector_must_fail()
    {
        //add a collectorA to the project
        $collectorA = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collectorA->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        //add a collectorB to the project
        $collectorB = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collectorB->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question
        $inputText = '';
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $inputText = $input;
                break;
            }
        }

        //create entry with the collector A role
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($collectorA);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $collectorA,
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
            if ($ref === $inputText['ref']) {
                $editedInputAnswer = [
                    'answer' =>  $this->entryGenerator->createAnswer($inputText, $entryFromDB->uuid),
                    'was_jumped' => false
                ];
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputText['ref'], $editedInputAnswer);

        $response = [];
        try {
            Auth::guard('api_internal')->login($collectorB);
            $response[] = $this->actingAs($collectorB)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(400);
            $response[0]->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_54",
                            "source" => "upload",
                            "title" => "User not authorised to edit this entry."
                        ]
                    ]
                ]
            );

            //get edited entry from db
            $editedEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();
            //assert entry answer was NOT edited
            $editedAnswers = json_decode($editedEntryFromDB->entry_data, true)['entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputText['ref']) {
                    $this->assertNotEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert entry belongs to collector A
            $this->assertEquals($entryFromDB->user_id, $collectorA->id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_entry_text_by_web_upload_jumping_branches()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first text question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                $inputRef = $input['ref'];
                $inputText = $input;
                break;
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_internal')->login($this->user);
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

        //create some branch entries
        $branches = [];
        $branchRefs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branches[] = $input;
                $branchRefs[] = $input['ref'];
            }
        }


        $numOfBranches = rand(2, 5);
        $branchEntryPayloads = [];
        for ($i = 0; $i < $numOfBranches; $i++) {
            foreach ($branches as $branch) {
                $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                    $formRef,
                    $branch['branch'],
                    $entryFromDB->uuid,
                    $branch['ref']
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
        }

        //assert rows are created
        $this->assertCount(
            $numOfBranches * sizeof($branches),
            BranchEntry::where('project_id', $this->project->id)->get()
        );

        //update owner entry branch counts
        $branchCounts = [];
        foreach ($branches as $branch) {
            $branchCounts[$branch['ref']] = $numOfBranches;
        }
        $entryFromDB->branch_counts = json_encode($branchCounts);
        $entryFromDB->save();

        //try to upload payload text answer edited and jumped branches
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $entryPayloads[0]['data']['entry']['answers'];
        $this->setEditedAnswerHierarchy($payloadAnswers, $entryPayloads[0], $inputRef, $editedInputAnswer);

        //set a random branch as jumped
        $jumpedBranchRef = $this->faker->randomElement($branchRefs);
        $entryPayloads[0]['data']['entry']['answers'][$jumpedBranchRef]['was_jumped'] = true;

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entryPayloads[0]);
            $response[0]->assertStatus(200);

            $response[0]->assertExactJson(
                [
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);


            $branchCounts = json_decode($editedEntryFromDB->branch_counts, true);
            foreach ($branchRefs as $branchRef) {
                if ($branchRef !== $jumpedBranchRef) {
                    //assert other branches are not touched
                    $this->assertEquals(
                        $numOfBranches,
                        BranchEntry::where(
                            'owner_input_ref',
                            $branchRef
                        )
                            ->where(
                                'owner_uuid',
                                $entryFromDB->uuid
                            )
                            ->count()
                    );
                    //assert branch counts are correct
                    $this->assertEquals($numOfBranches, $branchCounts[$branchRef]);
                } else {
                    //assert jumped branches were deleted
                    $this->assertEquals(
                        0,
                        BranchEntry::where(
                            'owner_input_ref',
                            $jumpedBranchRef
                        )
                            ->where(
                                'owner_uuid',
                                $entryFromDB->uuid
                            )
                            ->count()
                    );
                    //assert branch counts are updated accordingly
                    $this->assertEquals(0, $branchCounts[$branchRef]);
                }
            }
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }
}
