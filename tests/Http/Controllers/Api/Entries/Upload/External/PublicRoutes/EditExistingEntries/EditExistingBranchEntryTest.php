<?php

namespace Http\Controllers\Api\Entries\Upload\External\PublicRoutes\EditExistingEntries;

use Auth;
use ec5\Libraries\Utilities\Common;
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
use Exception;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class EditExistingBranchEntryTest extends TestCase
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

    public function test_edit_existing_branch_entry_text_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch text question
        $inputRef = '';
        $ownerInputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload text answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputText, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_branch_entry_integer_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch integer question
        $inputRef = '';
        $ownerInputRef = '';
        $inputInteger = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload with answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputInteger, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_branch_entry_decimal_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch decimal question
        $inputRef = '';
        $ownerInputRef = '';
        $inputDecimal = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload with answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDecimal, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_branch_entry_phone_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch phone question
        $inputRef = '';
        $ownerInputRef = '';
        $inputPhone = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload with answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputPhone, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_branch_entry_date_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch date question
        $inputRef = '';
        $ownerInputRef = '';
        $inputDate = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload with answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDate, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_branch_entry_time_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch time question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTime = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.time')) {
                        $inputRef = $input['ref'];
                        $inputTime = $input;
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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload with answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTime, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_branch_entry_dropdown_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch dropdown question
        $inputRef = '';
        $ownerInputRef = '';
        $inputDropdown = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload with answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputDropdown, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_branch_entry_radio_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch radio question
        $inputRef = '';
        $ownerInputRef = '';
        $inputRadio = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload with answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputRadio, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_branch_entry_checkbox_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch checkbox question
        $inputRef = '';
        $ownerInputRef = '';
        $inputCheckbox = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload with answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputCheckbox, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_edit_existing_branch_entry_searchsingle_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch searchsingle question
        $inputRef = '';
        $ownerInputRef = '';
        $inputSearchsingle = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.searchsingle')) {
                        $inputRef = $input['ref'];
                        $inputSearchsingle = $input;
                        break;
                    }
                }
            }
        }

        dd($inputSearchsingle);


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

        //generate a branch entry for this owner
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
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


        //try to upload branch payload with answer edited
        $editedAnswers = json_decode($branchEntryFromDB->entry_data, true)['branch_entry']['answers'];
        foreach ($editedAnswers as $ref => $existingAnswer) {
            if ($ref === $inputRef) {
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputSearchsingle, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswer($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert user matches
            $this->assertEquals($branchEntryFromDB->user_id, $editedBranchEntryFromDB->user_id);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    private function setEditedAnswer($payloadAnswers, &$payload, $inputRef, $editedInputAnswer)
    {
        foreach ($payloadAnswers as $ref => $payloadAnswer) {
            if ($ref === $inputRef) {
                $payload['data']['branch_entry']['answers'][$inputRef] = $editedInputAnswer;
                break;
            }
        }
    }
}