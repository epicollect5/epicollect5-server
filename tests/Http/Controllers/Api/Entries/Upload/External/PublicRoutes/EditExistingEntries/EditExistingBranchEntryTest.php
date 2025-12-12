<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PublicRoutes\EditExistingEntries;

use Auth;
use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
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
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Random\RandomException;
use Tests\TestCase;
use Throwable;

class EditExistingBranchEntryTest extends TestCase
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
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_text_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch text question
        $inputRef = '';
        $ownerInputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $inputRef = $branchInput['ref'];
                        $inputText = $branchInput;
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_integer_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];

        //get the first branch integer question
        $inputRef = '';
        $ownerInputRef = '';
        $inputInteger = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.integer')) {
                        $inputRef = $branchInput['ref'];
                        $inputInteger = $branchInput;
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_decimal_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];

        //get the first branch decimal question
        $inputRef = '';
        $ownerInputRef = '';
        $inputDecimal = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.decimal')) {
                        $inputRef = $branchInput['ref'];
                        $inputDecimal = $branchInput;
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_phone_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];

        //get the first branch phone question
        $inputRef = '';
        $ownerInputRef = '';
        $inputPhone = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.phone')) {
                        $inputRef = $branchInput['ref'];
                        $inputPhone = $branchInput;
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_date_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];

        //get the first branch date question
        $inputRef = '';
        $ownerInputRef = '';
        $inputDate = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.date')) {
                        $inputRef = $branchInput['ref'];
                        $inputDate = $branchInput;
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_time_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];
        //get the first branch time question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTime = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.time')) {
                        $inputRef = $branchInput['ref'];
                        $inputTime = $branchInput;
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_dropdown_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];
        //get the first branch dropdown question
        $inputRef = '';
        $ownerInputRef = '';
        $inputDropdown = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.dropdown')) {
                        $inputRef = $branchInput['ref'];
                        $inputDropdown = $branchInput;
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_radio_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];
        //get the first branch radio question
        $inputRef = '';
        $ownerInputRef = '';
        $inputRadio = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.radio')) {
                        $inputRef = $branchInput['ref'];
                        $inputRadio = $branchInput;
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_checkbox_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];
        //get the first branch checkbox question
        $inputRef = '';
        $ownerInputRef = '';
        $inputCheckbox = [];
        $editedInputAnswer = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.checkbox')) {
                        $inputRef = $branchInput['ref'];
                        $inputCheckbox = $branchInput;
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_searchsingle_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];
        //add a searchsingle question to the first branch
        $inputRef = '';
        $ownerInputRef = '';
        $inputSearchsingle = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                $branchInputs[] = ProjectDefinitionGenerator::createSearchSingleInput($ownerInputRef);
                foreach ($branchInputs as $branchIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.searchsingle')) {
                        $inputRef = $branchInput['ref'];
                        $inputSearchsingle = $branchInput;
                        $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchIndex] = $branchInput;
                        break;
                    }
                }
                //update project structures, we do this as the branch does not come with a searchsingle question out of the box
                $projectExtraService = new ProjectExtraService();
                $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

                ProjectStructure::where('project_id', $this->project->id)->update([
                    'project_definition' => json_encode($this->projectDefinition['data']),
                    'project_extra' => json_encode($projectExtra),
                ]);
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputSearchsingle, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_searchmultiple_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];

        //add a searchmultiple question to the first branch
        $inputRef = '';
        $ownerInputRef = '';
        $inputSearchsingle = [];
        $editedInputAnswer = [];
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                $branchInputs[] = ProjectDefinitionGenerator::createSearchMultipleInput($ownerInputRef);
                foreach ($branchInputs as $branchIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.searchmultiple')) {
                        $inputRef = $branchInput['ref'];
                        $inputSearchsingle = $branchInput;
                        $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchIndex] = $branchInput;
                        break;
                    }
                }
                //update project structures, we do this as the branch does not come with a searchsingle question out of the box
                $projectExtraService = new ProjectExtraService();
                $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);

                ProjectStructure::where('project_id', $this->project->id)->update([
                    'project_definition' => json_encode($this->projectDefinition['data']),
                    'project_extra' => json_encode($projectExtra),
                ]);
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputSearchsingle, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_textbox_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch text question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.textarea')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_location_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch location question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.location')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

            //get edited entry from db (imp: we use the BranchEntry model with getEntryDataAttribute() accessor)
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

            //assert geo json answer was edited (imp: we use the Entry model with getEntryDataAttribute() accessor)
            $editedGeoJsonAnswers = json_decode($editedBranchEntryFromDB->geo_json_data, true);
            foreach ($editedGeoJsonAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {

                    //get latitude and longitude from edited answer (entry_data)
                    $editedLatitude = $editedAnswers[$inputRef]['answer']['latitude'];
                    $editedLongitude = $editedAnswers[$inputRef]['answer']['longitude'];

                    //assert latitude and longitude match the geometry in geo_json_data
                    $this->assertEquals($editedLatitude, $editedAnswer['geometry']['coordinates'][1]);
                    $this->assertEquals($editedLongitude, $editedAnswer['geometry']['coordinates'][0]);
                    break;
                }
            }

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_audio_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch location question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.audio')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_photo_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch location question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.photo')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_video_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch location question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.video')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_barcode_by_app_upload_same_user()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch location question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.barcode')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_text_by_app_upload_manager_role()
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

        //get the first branch location question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
                        break;
                    }
                }
            }
        }

        //create entry with the creator role
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
            Auth::logout();
        }

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner entry, with the manager role
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($manager);
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            //upload asd manager
            $response[] = $this->actingAs($manager)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_text_by_app_upload_curator_role()
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

        //get the first branch text question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
                        break;
                    }
                }
            }
        }

        //create entry with the creator role
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
            Auth::logout();
        }

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner entry, with the curator role
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($curator);
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            //upload as curator
            $response[] = $this->actingAs($curator)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_text_by_app_upload_same_collector()
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

        //get the first branch text question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
                        break;
                    }
                }
            }
        }

        //create entry with the creator role
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
            Auth::logout();
        }

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner entry, with the collector role
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($collector);
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
                $ownerInputRef
            );
            $entryRowBundle = $this->entryGenerator->createBranchEntryRow(
                $collector,
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            //upload as collector
            $response[] = $this->actingAs($collector)->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            //assert user is collector
            $this->assertEquals($branchEntryFromDB->user_id, $collector->id);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_edit_existing_branch_entry_text_by_app_upload_different_collector_must_fail()
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

        //get the first branch text question
        $inputRef = '';
        $ownerInputRef = '';
        $inputTextarea = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $inputRef = $branchInput['ref'];
                        $inputTextarea = $branchInput;
                        break;
                    }
                }
            }
        }

        //create entry with the collector A role
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($collectorA);
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
            Auth::logout();
        }

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //generate a branch entry for this owner entry, with the collector role
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($collectorA);
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branchInputs,
                $entryFromDB->uuid,
                $ownerInputRef
            );
            $entryRowBundle = $this->entryGenerator->createBranchEntryRow(
                $collectorA,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $branchEntryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $branchEntryPayloads[$i]
            );
            Auth::logout();
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
                $editedInputAnswer = $this->entryGenerator->createAnswer($inputTextarea, $entryFromDB->uuid);
                break;
            }
        }

        $payloadAnswers = $branchEntryPayloads[0]['data']['branch_entry']['answers'];
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            //upload as collector B via the external api guard
            $response[] = $this->actingAs($collectorB, 'api_external')->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was NOT edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertNotEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }
            //assert entry belongs to collector A
            $this->assertEquals($editedBranchEntryFromDB->user_id, $branchEntryFromDB->user_id);
            $this->assertEquals($editedBranchEntryFromDB->user_id, $collectorA->id);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     * @throws RandomException
     */
    public function test_edit_existing_branch_entry_text_by_app_upload_same_device()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch text question
        $inputRef = '';
        $ownerInputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $inputRef = $branchInput['ref'];
                        $inputText = $branchInput;
                        break;
                    }
                }
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        $deviceId = Common::generateRandomHex();
        for ($i = 0; $i < 1; $i++) {
            Auth::guard('api_external')->login($this->user);
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $deviceId);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                null,
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
                $ownerInputRef,
                $deviceId
            );
            $entryRowBundle = $this->entryGenerator->createBranchEntryRow(
                null,
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            //perform an app upload without the user but with the same device ID
            $response[] = $this->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }

            //assert no user was assigned
            $this->assertEquals(0, $entryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     * @throws RandomException
     */
    public function test_edit_existing_branch_entry_text_by_app_upload_different_device_fails()
    {
        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch text question
        $inputRef = '';
        $ownerInputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $inputRef = $branchInput['ref'];
                        $inputText = $branchInput;
                        break;
                    }
                }
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        $deviceId = Common::generateRandomHex();
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $deviceId);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                null,
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
                $ownerInputRef,
                $deviceId
            );
            $entryRowBundle = $this->entryGenerator->createBranchEntryRow(
                null,
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        //change device id for payload
        $branchEntryPayloads[0]['data']['branch_entry']['device_id'] = Common::generateRandomHex();

        $response = [];
        try {
            //perform an app upload without the user but with the same device ID
            $response[] = $this->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was NOT edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertNotEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }

            //assert no user was assigned
            $this->assertEquals(0, $entryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     * @throws RandomException
     */
    public function test_edit_existing_branch_entry_text_by_app_upload_same_device_logged_in_collector()
    {
        $collector = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        //get project definition
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');

        //get the first branch text question
        $inputRef = '';
        $ownerInputRef = '';
        $inputText = [];
        $editedInputAnswer = [];
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.text')) {
                        $inputRef = $branchInput['ref'];
                        $inputText = $branchInput;
                        break;
                    }
                }
            }
        }

        //create entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        $deviceId = Common::generateRandomHex();
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef, $deviceId);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                null,
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
                $ownerInputRef,
                $deviceId
            );
            $entryRowBundle = $this->entryGenerator->createBranchEntryRow(
                null,
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
        $this->setEditedAnswerBranch($payloadAnswers, $branchEntryPayloads[0], $inputRef, $editedInputAnswer);

        $response = [];
        try {
            //perform an app upload with the user, the same device, should update user ID
            $response[] = $this->actingAs($collector, 'api_external')->post($this->endpoint . $this->project->slug, $branchEntryPayloads[0]);
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
            $editedBranchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
            //assert entry answer was edited
            $editedAnswers = json_decode($editedBranchEntryFromDB->entry_data, true)['branch_entry']['answers'];
            foreach ($editedAnswers as $ref => $editedAnswer) {
                if ($ref === $inputRef) {
                    $this->assertEquals($editedInputAnswer, $editedAnswer);
                    break;
                }
            }

            //assert the user ID was updated
            $this->assertEquals($collector->id, $editedBranchEntryFromDB->user_id);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }
}
