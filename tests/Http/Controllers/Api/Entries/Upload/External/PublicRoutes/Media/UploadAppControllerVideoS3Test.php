<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PublicRoutes\Media;

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
use Exception;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Random\RandomException;
use Tests\TestCase;
use Throwable;

/* We cannot do multiple post requests in the same test method,
   as the app boots only once, and we are going to have side effects
   https://github.com/laravel/framework/issues/27060
   therefore, we use concatenation of @depends
 */

class UploadAppControllerVideoS3Test extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private string $endpoint = 'api/upload/';

    /**
     * @throws RandomException
     */
    public function setUp(): void
    {
        parent::setUp();
        //set storage (and all disks) to S3
        $this->overrideStorageDriver('s3');
        $this->faker = Faker::create();

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
        $projectDefinition = ProjectDefinitionGenerator::createProject(2);
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
                'project_id' => $project->id
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

    public function test_it_should_upload_a_top_hierarchy_video()
    {
        $response = [];
        $inputRef = null;
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first video question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                'video',
                $inputRef
            );

            // Get the temporary file path from the UploadedFile
            $tempFilePath = $payload['name']->getRealPath();
            $expectedBytes = filesize($tempFilePath);

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post(
                $this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );

            //assert file is uploaded
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(1, $videos);

            //assert storage stats are updated
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals($expectedBytes, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->audio_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals($expectedBytes, $projectStats->video_bytes);
            $this->assertEquals(1, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(1, $projectStats->video_files);

            //clean up
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_upload_a_child_entry_video()
    {
        $response = [];
        $inputRef = null;
        $parentFormRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        try {
            //get child childFormRef
            $childFormRef = array_get($this->projectDefinition, 'data.project.forms.1.ref');
            if (is_null($childFormRef)) {
                throw new Exception('This project does not have a child form with index 1');
            }

            //get first video question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.1.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
            $entryPayloads = [];
            for ($i = 0; $i < 1; $i++) {
                $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($parentFormRef);
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

            $parentEntryUuid = $entryPayloads[0]['data']['id'];

            $childEntryPayloads = [];
            for ($i = 0; $i < 1; $i++) {
                $childEntryPayloads[$i] = $this->entryGenerator->createChildEntryPayload($childFormRef, $parentFormRef, $parentEntryUuid);
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

            $filename = $childEntryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $childEntryUuid = $childEntryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload
            $payload = $this->entryGenerator->createFilePayload(
                $childFormRef,
                $childEntryUuid,
                $filename,
                'video',
                $inputRef
            );
            //add parent references
            $payload['data']['file_entry']['relationships']['parent']['data'] = [
                'parent_form_ref' => $parentFormRef,
                'parent_entry_uuid' => $parentEntryUuid
            ];

            // Get the temporary file path from the UploadedFile
            $tempFilePath = $payload['name']->getRealPath();
            $expectedBytes = filesize($tempFilePath);

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post(
                $this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );

            //assert file is uploaded
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(1, $videos);

            //assert storage stats are updated
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals($expectedBytes, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->audio_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals($expectedBytes, $projectStats->video_bytes);
            $this->assertEquals(1, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(1, $projectStats->video_files);

            //clean up
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_not_allow_other_than_mp4()
    {
        $response = [];
        $inputRef = null;
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first video question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                'ogg',
                $inputRef,
                'Android',
                '.ogg'
            );

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post(
                $this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_47",
                                "title" => "File format incorrect.",
                                "source" => "type"
                            ]
                        ]
                    ]
                );

            //assert file is not uploaded
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(0, $videos);

            //assert storage stats are not updated
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->audio_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->video_bytes);
            $this->assertEquals(0, $projectStats->total_files);

            //clean up
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_it_should_upload_a_branch_video()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];
        $ownerInputRef = null;
        $branchInputRef = null;
        //get first video question in the branch
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.video')) {
                        $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['uniqueness'] = 'form';
                        $branchInputRef = $branchInput['ref'];
                        break 2;
                    }
                }
            }
        }

        //create owner entry
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

        $filename = $branchEntryPayloads[0]['data']['branch_entry']['answers'][$branchInputRef]['answer'];
        $branchEntryUuid = $branchEntryPayloads[0]['data']['branch_entry']['entry_uuid'];

        $response = [];
        try {
            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $branchEntryUuid,
                $filename,
                'video',
                $branchInputRef
            );

            //add branch references
            $payload['data']['file_entry']['relationships']['branch'] = [
                'data' => [
                    'owner_entry_uuid' => $ownerEntryFromDB->uuid,
                    'owner_input_ref' => $ownerInputRef
                ]
            ];

            // Get the temporary file path from the UploadedFile
            $tempFilePath = $payload['name']->getRealPath();
            $expectedBytes = filesize($tempFilePath);

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post(
                $this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );

            //assert file is uploaded
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(1, $videos);

            //assert storage stats are updated
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals($expectedBytes, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->audio_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals($expectedBytes, $projectStats->video_bytes);
            $this->assertEquals(1, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(1, $projectStats->video_files);

            //clean up
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_it_should_ignore_missing_branch_entry()
    {
        //get branch inputs
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $branchInputs = [];
        $ownerInputRef = null;
        $branchInputRef = null;
        //get first video question in the branch
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                foreach ($branchInputs as $branchInputIndex => $branchInput) {
                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.video')) {
                        $this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]['branch'][$branchInputIndex]['uniqueness'] = 'form';
                        $branchInputRef = $branchInput['ref'];
                        break 2;
                    }
                }
            }
        }

        //create owner entry
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

        $filename = $branchEntryPayloads[0]['data']['branch_entry']['answers'][$branchInputRef]['answer'];
        $branchEntryUuid = $branchEntryPayloads[0]['data']['branch_entry']['entry_uuid'];

        $response = [];
        try {
            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $branchEntryUuid,
                $filename,
                'video',
                $branchInputRef
            );

            //add branch references
            $payload['data']['file_entry']['relationships']['branch'] = [
                'data' => [
                    'owner_entry_uuid' => $ownerEntryFromDB->uuid,
                    'owner_input_ref' => $ownerInputRef
                ]
            ];

            //delete branch entry from DB
            BranchEntry::where('uuid', $branchEntryUuid)->delete();

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post(
                $this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );

            //assert file is not uploaded
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(0, $videos);

            //assert storage stats are not updated
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->audio_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->video_bytes);
            $this->assertEquals(0, $projectStats->total_files);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_ignore_missing_entry()
    {
        $response = [];
        $inputRef = null;
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first video question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                'video',
                $inputRef
            );

            //delete the entry
            Entry::where('uuid', $entryUuid)->delete();

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post(
                $this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );

            //assert file is not uploaded
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(0, $videos);

            //assert storage stats are not updated
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->audio_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->video_bytes);
            $this->assertEquals(0, $projectStats->total_files);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_ignore_video_question_deleted()
    {
        $response = [];
        $inputRef = null;
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first video question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //delete the video question
            foreach ($inputs as $index => $input) {
                if ($input['ref'] === $inputRef) {
                    unset($this->projectDefinition['data']['project']['forms'][0]['inputs'][$index]);
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

            //generate a fake payload for the top parent form
            //imp: done after modifying the project as that changes the project version
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                'video',
                $inputRef
            );

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post(
                $this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );


            //assert file is not uploaded
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(0, $videos);

            //assert storage stats are not updated
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->audio_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->video_bytes);
            $this->assertEquals(0, $projectStats->total_files);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

}
