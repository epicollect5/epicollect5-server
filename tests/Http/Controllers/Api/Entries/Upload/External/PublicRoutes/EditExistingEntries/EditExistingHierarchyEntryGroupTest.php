<?php

namespace Http\Controllers\Api\Entries\Upload\External\PublicRoutes\EditExistingEntries;

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

class EditExistingHierarchyEntryGroupTest extends TestCase
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

    public function test_edit_existing_entry_group_text_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group text question
        $inputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.text')) {
                        $inputRef = $input['ref'];
                        $inputText = $input;
                        break;
                    }
                }
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

        //try to upload payload text answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_integer_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group integer question
        $inputRef = '';
        $inputInteger = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.integer')) {
                        $inputRef = $input['ref'];
                        $inputInteger = $input;
                        break;
                    }
                }
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

        //try to upload payload group integer answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputInteger, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_decimal_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group decimal question
        $inputRef = '';
        $inputDecimal = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.decimal')) {
                        $inputRef = $input['ref'];
                        $inputDecimal = $input;
                        break;
                    }
                }
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

        //try to upload payload group integer answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDecimal, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_phone_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group decimal question
        $inputRef = '';
        $inputPhone = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.phone')) {
                        $inputRef = $input['ref'];
                        $inputPhone = $input;
                        break;
                    }
                }
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

        //try to upload payload group phone answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputPhone, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_date_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group date question
        $inputRef = '';
        $inputDate = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.date')) {
                        $inputRef = $input['ref'];
                        $inputDate = $input;
                        break;
                    }
                }
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

        //try to upload payload group date answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDate, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_time_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group time question
        $inputRef = '';
        $inputDate = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                        $inputRef = $input['ref'];
                        $inputDate = $input;
                        break;
                    }
                }
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

        //try to upload payload group time answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDate, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_dropdown_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group time question
        $inputRef = '';
        $inputDropdown = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.dropdown')) {
                        $inputRef = $input['ref'];
                        $inputDropdown = $input;
                        break;
                    }
                }
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

        //try to upload payload group dropdown answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDropdown, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_radio_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group time question
        $inputRef = '';
        $inputRadio = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.radio')) {
                        $inputRef = $input['ref'];
                        $inputRadio = $input;
                        break;
                    }
                }
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

        //try to upload payload group radio answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputRadio, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_checkbox_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group time question
        $inputRef = '';
        $inputCheckbox = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.checkbox')) {
                        $inputRef = $input['ref'];
                        $inputCheckbox = $input;
                        break;
                    }
                }
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

        //try to upload payload group checkbox answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputCheckbox, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_searchsingle_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group time question
        $inputRef = '';
        $inputSearchsingle = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.searchsingle')) {
                        $inputRef = $input['ref'];
                        $inputSearchsingle = $input;
                        break;
                    }
                }
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

        //try to upload payload group searchsingle answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputSearchsingle, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_searchmultiple_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group searchmultiple question
        $inputRef = '';
        $inputSearchmultiple = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.searchmultiple')) {
                        $inputRef = $input['ref'];
                        $inputSearchmultiple = $input;
                        break;
                    }
                }
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

        //try to upload payload group searchmultiple answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputSearchmultiple, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_textbox_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group searchmultiple question
        $inputRef = '';
        $inputTextbox = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.textarea')) {
                        $inputRef = $input['ref'];
                        $inputTextbox = $input;
                        break;
                    }
                }
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

        //try to upload payload group textbox answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextbox, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_location_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group location question
        $inputRef = '';
        $inputTextbox = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
                        $inputRef = $input['ref'];
                        $inputTextbox = $input;
                        break;
                    }
                }
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

        //try to upload payload group location answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextbox, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_photo_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group location question
        $inputRef = '';
        $inputPhoto = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.photo')) {
                        $inputRef = $input['ref'];
                        $inputPhoto = $input;
                        break;
                    }
                }
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

        //try to upload payload group photo answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputPhoto, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_audio_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group location question
        $inputRef = '';
        $inputAudio = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.audio')) {
                        $inputRef = $input['ref'];
                        $inputAudio = $input;
                        break;
                    }
                }
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

        //try to upload payload group audio answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputAudio, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_video_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group location question
        $inputRef = '';
        $inputVideo = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
                        $inputRef = $input['ref'];
                        $inputVideo = $input;
                        break;
                    }
                }
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

        //try to upload payload group video answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputVideo, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_entry_group_barcode_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first group location question
        $inputRef = '';
        $inputBarcode = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.barcode')) {
                        $inputRef = $input['ref'];
                        $inputBarcode = $input;
                        break;
                    }
                }
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

        //try to upload payload group video answer edited
        $editedAnswers = json_decode($entryFromDB->entry_data, true)['entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputBarcode, $entryFromDB->uuid);
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
            //assert user matches
            $this->assertEquals($entryFromDB->user_id, $editedEntryFromDB->user_id);

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