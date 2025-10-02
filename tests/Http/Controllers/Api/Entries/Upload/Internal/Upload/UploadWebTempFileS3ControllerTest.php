<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal\Upload;

use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Services\Project\ProjectExtraService;
use ec5\Traits\Assertions;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

/* We cannot do multiple post requests in the same test method,
   as the app boots only once, and we are going to have side effects
   https://github.com/laravel/framework/issues/27060
   therefore, we use concatenation of @depends
 */

class UploadWebTempFileS3ControllerTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private Generator $faker;
    private User $user;
    private string $role;
    private Project $project;
    private array $projectDefinition;
    private array $projectExtra;
    private EntryGenerator $entryGenerator;
    private string $endpoint = 'api/internal/web-upload-file';

    public function setUp(): void
    {
        parent::setUp();
        //set storage (and all disks) to S3
        $this->overrideStorageDriver('s3');
        $this->faker = Faker::create();

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
    }

    public function test_it_should_upload_a_photo_file()
    {
        $response = [];
        $inputRef = null;
        $mediaType = 'photo';
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first photo question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.'.$mediaType)) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                $mediaType,
                $inputRef
            );

            $response[] = $this->actingAs($this->user)->post(
                $this->endpoint . '/'.$this->project->slug,
                [
                    'data' => $payload['data'],
                    'file' => $payload['name']
                ]
            );

            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_242",
                            "title" => "File Temporarily Uploaded"
                        ]
                    ]
                );

            //assert file is uploaded to the temp folder
            $photos = Storage::disk('temp')->files($mediaType.'/'.$this->project->ref);
            $this->assertCount(1, $photos);

            //assert storage stats are not updated yet, as the entry is not finalised
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(0, $projectStats->video_files);

            Storage::disk('temp')->deleteDirectory($mediaType.'/'.$this->project->ref);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_upload_an_audio_file()
    {
        $response = [];
        $inputRef = null;
        $mediaType = 'audio';
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first photo question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.'.$mediaType)) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                $mediaType,
                $inputRef
            );

            $response[] = $this->actingAs($this->user)->post(
                $this->endpoint . '/'.$this->project->slug,
                [
                    'data' => $payload['data'],
                    'file' => $payload['name']
                ]
            );

            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_242",
                            "title" => "File Temporarily Uploaded"
                        ]
                    ]
                );

            //assert file is uploaded to the temp folder
            $audios = Storage::disk('temp')->files($mediaType.'/'.$this->project->ref);
            $this->assertCount(1, $audios);

            //assert storage stats are not updated yet, as the entry is not finalised
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(0, $projectStats->video_files);

            Storage::disk('temp')->deleteDirectory($mediaType.'/'.$this->project->ref);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_upload_a_video_file()
    {
        $response = [];
        $inputRef = null;
        $mediaType = 'video';
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first photo question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.'.$mediaType)) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                $mediaType,
                $inputRef
            );

            $response[] = $this->actingAs($this->user)->post(
                $this->endpoint . '/'.$this->project->slug,
                [
                    'data' => $payload['data'],
                    'file' => $payload['name']
                ]
            );

            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_242",
                            "title" => "File Temporarily Uploaded"
                        ]
                    ]
                );

            //assert file is uploaded to the temp folder
            $videos = Storage::disk('temp')->files($mediaType.'/'.$this->project->ref);
            $this->assertCount(1, $videos);

            //assert storage stats are not updated yet, as the entry is not finalised
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(0, $projectStats->video_files);

            Storage::disk('temp')->deleteDirectory($mediaType.'/'.$this->project->ref);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_catch_project_locked()
    {
        //set the project to locked
        $this->project->status = config('epicollect.strings.project_status.locked');
        $this->project->save();
        $response = [];
        $inputRef = null;
        $mediaType = 'photo';
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first photo question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.'.$mediaType)) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                $mediaType,
                $inputRef
            );

            $response[] = $this->actingAs($this->user)->post(
                $this->endpoint . '/'.$this->project->slug,
                [
                    'data' => $payload['data'],
                    'file' => $payload['name']
                ]
            );

            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                 "code" => "ec5_202",
                                 "source" => "temp-file-upload",
                                 "title" => "This project is locked."
                            ]
                        ]
                    ]
                );

            //assert file is not uploaded to the temp folder
            $photos = Storage::disk('temp')->files($mediaType.'/'.$this->project->ref);
            $this->assertCount(0, $photos);

            //assert storage stats are not updated yet, as the entry is not finalised
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(0, $projectStats->video_files);

            Storage::disk('temp')->deleteDirectory($mediaType.'/'.$this->project->ref);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_catch_project_trashed()
    {
        //set the project to trashed
        $this->project->status = config('epicollect.strings.project_status.trashed');
        $this->project->save();
        $response = [];
        $inputRef = null;
        $mediaType = 'photo';
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first photo question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.'.$mediaType)) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                $mediaType,
                $inputRef
            );

            $response[] = $this->actingAs($this->user)->post(
                $this->endpoint . '/'.$this->project->slug,
                [
                    'data' => $payload['data'],
                    'file' => $payload['name']
                ]
            );

            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_202",
                                "source" => "temp-file-upload",
                                "title" => "This project is locked."
                            ]
                        ]
                    ]
                );

            //assert file is not uploaded to the temp folder
            $photos = Storage::disk('temp')->files($mediaType.'/'.$this->project->ref);
            $this->assertCount(0, $photos);

            //assert storage stats are not updated yet, as the entry is not finalised
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(0, $projectStats->video_files);

            Storage::disk('temp')->deleteDirectory($mediaType.'/'.$this->project->ref);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_catch_role_viewer()
    {
        $this->project->access = config('epicollect.strings.project_access.private');
        $this->project->save();

        //create a viewer user
        $viewer = factory(User::class)->create();
        //create a viewer role
        $role = config('epicollect.strings.project_roles.viewer');
        factory(ProjectRole::class)->create([
            'user_id' => $viewer->id,
            'project_id' => $this->project->id,
            'role' => $role
        ]);
        $response = [];
        $inputRef = null;
        $mediaType = 'photo';
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first photo question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.'.$mediaType)) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                $mediaType,
                $inputRef
            );

            $response[] = $this->actingAs($viewer)->post(
                $this->endpoint . '/'.$this->project->slug,
                [
                    'data' => $payload['data'],
                    'file' => $payload['name']
                ]
            );

            $response[0]->assertStatus(400)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_78",
                                "source" => "temp-file-upload",
                                "title" => "This project is private. \n You need permission to access it."
                            ]
                        ]
                    ]
                );

            //assert file is not uploaded to the temp folder
            $photos = Storage::disk('temp')->files($mediaType.'/'.$this->project->ref);
            $this->assertCount(0, $photos);

            //assert storage stats are not updated yet, as the entry is not finalised
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(0, $projectStats->video_files);

            Storage::disk('temp')->deleteDirectory($mediaType.'/'.$this->project->ref);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_catch_user_not_logged_in_or_session_expired()
    {
        $this->project->access = config('epicollect.strings.project_access.private');
        $this->project->save();

        //create a manager user
        $manager = factory(User::class)->create();
        //create a viewer role
        $role = config('epicollect.strings.project_roles.manager');
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => $role
        ]);
        $response = [];
        $inputRef = null;
        $mediaType = 'photo';
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first photo question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.'.$mediaType)) {
                    $inputRef = $input['ref'];
                }
            }

            //create parent entry
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

            $filename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $entryUuid = $entryPayloads[0]['data']['entry']['entry_uuid'];

            //generate a fake payload for the top parent form
            $payload = $this->entryGenerator->createFilePayload(
                $formRef,
                $entryUuid,
                $filename,
                $mediaType,
                $inputRef
            );

            //do not auth any user to simulate session expired
            $response[] = $this->post(
                $this->endpoint . '/'.$this->project->slug,
                [
                    'data' => $payload['data'],
                    'file' => $payload['name']
                ]
            );

            $response[0]->assertStatus(404)
                ->assertExactJson(
                    [
                        "errors" => [
                            [
                                "code" => "ec5_77",
                                "source" => "middleware",
                                "title" => "This project is private. Please log in."
                            ]
                        ]
                    ]
                );

            //assert file is not uploaded to the temp folder
            $photos = Storage::disk('temp')->files($mediaType.'/'.$this->project->ref);
            $this->assertCount(0, $photos);

            //assert storage stats are not updated yet, as the entry is not finalised
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(0, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals(0, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(0, $projectStats->video_files);

            Storage::disk('temp')->deleteDirectory($mediaType.'/'.$this->project->ref);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

}
