<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal\Version;

use Carbon\Carbon;
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
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class ProjectVersionEntriesTest extends TestCase
{
    use DatabaseTransactions, Assertions;

    private $endpoint = 'api/internal/web-upload/';

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

    public function test_catch_project_version_out_of_date_data()
    {
        //create entry
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

        $payload = $this->entryGenerator->createParentEntryPayload($formRef);

        //imp: wait a few seconds so the timestamp is not the same
        sleep(3);

        //imp: update project in db to trigger a version update
        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);
        ProjectStructure::where('project_id', $this->project->id)->update([
            'project_definition' => json_encode($this->projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
            'updated_at' => Carbon::now()
        ]);

        $response = [];
        try {
            //perform an app upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_201",
                                "title" => "Project version out of date.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_catch_project_version_out_of_date_photo()
    {
        $response = [];
        $inputRef = null;
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first photo question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.photo')) {
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
                'photo',
                $inputRef
            );

            //imp: wait a few seconds so the timestamp is not the same
            sleep(3);
            //imp:update project in db to trigger a version update
            $projectExtraService = new ProjectExtraService();
            $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);
            ProjectStructure::where('project_id', $this->project->id)->update([
                'project_definition' => json_encode($this->projectDefinition['data']),
                'project_extra' => json_encode($projectExtra),
                'updated_at' => Carbon::now()
            ]);

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post($this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_201",
                                "title" => "Project version out of date.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );

            //assert file is NOT uploaded
            $photos = Storage::disk('entry_original')->files($this->project->ref);
            $this->assertCount(0, $photos);
            $photos = Storage::disk('entry_thumb')->files($this->project->ref);
            $this->assertCount(0, $photos);
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_catch_project_version_out_of_date_audio()
    {
        $response = [];
        $inputRef = null;
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get the first audio question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.audio')) {
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
                'audio',
                $inputRef
            );

            //imp: wait a few seconds so the timestamp is not the same
            sleep(3);
            //imp:update project in db to trigger a version update
            $projectExtraService = new ProjectExtraService();
            $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);
            ProjectStructure::where('project_id', $this->project->id)->update([
                'project_definition' => json_encode($this->projectDefinition['data']),
                'project_extra' => json_encode($projectExtra),
                'updated_at' => Carbon::now()
            ]);

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post($this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_201",
                                "title" => "Project version out of date.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );

            //assert file is NOT uploaded
            $audios = Storage::disk('audio')->files($this->project->ref);
            $this->assertCount(0, $audios);

        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_catch_project_version_out_of_date_video()
    {
        $response = [];
        $inputRef = null;
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get the first video question
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
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
                'video',
                $inputRef
            );

            //imp: wait a few seconds so the timestamp is not the same
            sleep(3);
            //imp:update project in db to trigger a version update
            $projectExtraService = new ProjectExtraService();
            $projectExtra = $projectExtraService->generateExtraStructure($this->projectDefinition['data']);
            ProjectStructure::where('project_id', $this->project->id)->update([
                'project_definition' => json_encode($this->projectDefinition['data']),
                'project_extra' => json_encode($projectExtra),
                'updated_at' => Carbon::now()
            ]);

            //multipart upload from app with json encoded string and file (Cordova FileTransfer)
            $response[] = $this->post($this->endpoint . $this->project->slug,
                ['data' => json_encode($payload['data']), 'name' => $payload['name']],
                ['Content-Type' => 'multipart/form-data']
            );

            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_201",
                                "title" => "Project version out of date.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );

            //assert file is NOT uploaded
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(0, $videos);
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }


}