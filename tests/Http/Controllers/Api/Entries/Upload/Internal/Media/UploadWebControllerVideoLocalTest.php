<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal\Media;

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
use ec5\Services\Media\PhotoSaverService;
use ec5\Services\Project\ProjectExtraService;
use ec5\Traits\Assertions;
use Exception;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Random\RandomException;
use Tests\TestCase;
use Throwable;

/* We cannot do multiple post requests in the same test method,
   as the app boots only once, and we are going to have side effects
   https://github.com/laravel/framework/issues/27060
   therefore, we use concatenation of @depends
 */

class UploadWebControllerVideoLocalTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private string $endpoint = 'api/internal/web-upload/';
    private Generator $faker;
    private User $user;
    private string $role;
    private Project $project;
    private array $projectDefinition;
    private array $projectExtra;
    private string $deviceId;
    private EntryGenerator $entryGenerator;

    /**
     * @throws RandomException
     */
    public function setUp(): void
    {
        parent::setUp();
        //set storage (and all disks) to local
        $this->overrideStorageDriver('local');
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

            //Get an video file and save it to temp disk
            $videoFilename = $entryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $videoPath = '/video/'. $this->project->ref . '/' . $videoFilename;
            //get the test video file
            $sampleVideoFilePath = base_path('tests/Files/video.mp4');
            // Get the size before copying
            $videoFileSize = filesize($sampleVideoFilePath);

            Storage::disk('temp')->putFileAs(
                dirname($videoPath),            // target directory
                new File($sampleVideoFilePath), // source file
                basename($videoPath)            // target filename
            );

            $response[] = $this->actingAs($this->user)
                ->post(
                    $this->endpoint . $this->project->slug,
                    $entryPayloads[0]
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
            $photos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(1, $photos);

            //assert storage stats are updated
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(1, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals($videoFileSize, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(1, $projectStats->video_files);
            $this->assertEquals($videoFileSize, $projectStats->video_bytes);
            $this->assertEquals(0, $projectStats->audio_files);

            //delete temp folder
            Storage::disk('temp')->deleteDirectory('video/'.$this->project->ref);
            //deleted the file
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_upload_a_top_hierarchy_entry_with_multiple_files()
    {
        $response = [];
        $photoInputRef = null;
        $audioInputRef = null;
        $videoInputRef = null;
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //get first media questions
            $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.photo')) {
                    $photoInputRef = $input['ref'];
                }
                if ($input['type'] === config('epicollect.strings.inputs_type.audio')) {
                    $audioInputRef = $input['ref'];
                }
                if ($input['type'] === config('epicollect.strings.inputs_type.video')) {
                    $videoInputRef = $input['ref'];
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

            //create a photo file and save it to temp disk
            $filename = $entryPayloads[0]['data']['entry']['answers'][$photoInputRef]['answer'];
            $manager = ImageManager::imagick();
            // create a red 1024x768 canvas
            $image = $manager->create(1024, 768)->fill('#ff0000');
            // encode as jpeg with quality 70
            $data = $image->encode(new JpegEncoder(quality: 70));
            // save to temp disk
            $photoPath = '/photo/'. $this->project->ref . '/' . $filename;
            Storage::disk('temp')->put($photoPath, (string) $data);
            // process the image the same way PhotoSaverService handler does
            $realPath = Storage::disk('temp')->path($photoPath);
            $processedImage = PhotoSaverService::processImage($realPath, [1024, 768], 70);
            $expectedBytes = strlen($processedImage);

            //Get an audio file and save it to temp disk
            $audioFilename = $entryPayloads[0]['data']['entry']['answers'][$audioInputRef]['answer'];
            $audioPath = '/audio/'. $this->project->ref . '/' . $audioFilename;
            //get the test audio file
            $sampleAudioFilePath = base_path('tests/Files/audio.mp4');
            // Get the size before copying
            $audioFileSize = filesize($sampleAudioFilePath);

            Storage::disk('temp')->putFileAs(
                dirname($audioPath),            // target directory
                new File($sampleAudioFilePath), // source file
                basename($audioPath)            // target filename
            );

            //Get an audio file and save it to temp disk
            $videoFilename = $entryPayloads[0]['data']['entry']['answers'][$videoInputRef]['answer'];
            $videoPath = '/video/'. $this->project->ref . '/' . $videoFilename;
            //get the test audio file
            $sampleVideoFilePath = base_path('tests/Files/video.mp4');
            // Get the size before copying
            $videoFileSize = filesize($sampleVideoFilePath);

            Storage::disk('temp')->putFileAs(
                dirname($videoPath),            // target directory
                new File($sampleVideoFilePath), // source file
                basename($videoPath)            // target filename
            );

            $response[] = $this->actingAs($this->user)
                ->post(
                    $this->endpoint . $this->project->slug,
                    $entryPayloads[0]
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
            $photos = Storage::disk('photo')->files($this->project->ref);
            $this->assertCount(1, $photos);

            //assert storage stats are updated
            $totalBytes = $expectedBytes + $audioFileSize + $videoFileSize;
            $projectStats = ProjectStats::where('project_id', $this->project->id)->first();
            $this->assertEquals(3, $projectStats->total_files);
            $this->assertEquals(1, $projectStats->photo_files);
            $this->assertEquals($totalBytes, $projectStats->total_bytes);
            $this->assertEquals($expectedBytes, $projectStats->photo_bytes);
            $this->assertEquals(1, $projectStats->audio_files);
            $this->assertEquals($audioFileSize, $projectStats->audio_bytes);
            $this->assertEquals(1, $projectStats->video_files);
            $this->assertEquals($videoFileSize, $projectStats->video_bytes);

            //delete temp folder
            Storage::disk('temp')->deleteDirectory('photo/'.$this->project->ref);
            Storage::disk('temp')->deleteDirectory('audio/'.$this->project->ref);
            Storage::disk('temp')->deleteDirectory('video/'.$this->project->ref);
            //deleted the file
            Storage::disk('photo')->deleteDirectory($this->project->ref);
            Storage::disk('audio')->deleteDirectory($this->project->ref);
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


            $childEntryPayloads[0]['data']['entry']['relationships']['parent']['data'] = [
                'parent_form_ref' => $parentFormRef,
                'parent_entry_uuid' => $parentEntryUuid
            ];

            //Get an video file and save it to temp disk
            $videoFilename = $childEntryPayloads[0]['data']['entry']['answers'][$inputRef]['answer'];
            $videoPath = '/video/'. $this->project->ref . '/' . $videoFilename;
            //get the test video file
            $sampleVideoFilePath = base_path('tests/Files/video.mp4');
            // Get the size before copying
            $videoFileSize = filesize($sampleVideoFilePath);

            Storage::disk('temp')->putFileAs(
                dirname($videoPath),            // target directory
                new File($sampleVideoFilePath), // source file
                basename($videoPath)            // target filename
            );

            $response[] = $this->actingAs($this->user)
                ->post(
                    $this->endpoint . $this->project->slug,
                    $childEntryPayloads[0]
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
            $this->assertEquals(1, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals($videoFileSize, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(1, $projectStats->video_files);
            $this->assertEquals($videoFileSize, $projectStats->video_bytes);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(0, $projectStats->audio_bytes);

            //deleted the file
            Storage::disk('temp')->deleteDirectory('video/'.$this->project->ref);
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
        $videoPath = '/video/'. $this->project->ref . '/' . $filename;
        //get the test video file
        $sampleVideoFilePath = base_path('tests/Files/video.mp4');
        // Get the size before copying
        $videoFileSize = filesize($sampleVideoFilePath);

        Storage::disk('temp')->putFileAs(
            dirname($videoPath),            // target directory
            new File($sampleVideoFilePath), // source file
            basename($videoPath)            // target filename
        );


        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    $this->endpoint . $this->project->slug,
                    $branchEntryPayloads[0]
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
            $this->assertEquals(1, $projectStats->total_files);
            $this->assertEquals(0, $projectStats->photo_files);
            $this->assertEquals($videoFileSize, $projectStats->total_bytes);
            $this->assertEquals(0, $projectStats->photo_bytes);
            $this->assertEquals(1, $projectStats->video_files);
            $this->assertEquals($videoFileSize, $projectStats->video_bytes);
            $this->assertEquals(0, $projectStats->audio_files);
            $this->assertEquals(0, $projectStats->audio_bytes);

            Storage::disk('temp')->deleteDirectory('video/'.$this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }
}
