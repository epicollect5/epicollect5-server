<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Image;
use Intervention\Image\Drivers\Imagick\Encoders\JpegEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class MediaControllerLocalTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private User $user;
    private Project $project;
    private string $role;
    private array $projectDefinition;
    private EntryGenerator $entryGenerator;

    public function setUp(): void
    {
        parent::setUp();

        $roles = config('epicollect.strings.project_roles');

        //create fake user for testing
        $user = factory(User::class)->create();
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
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //create basic project definition
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'])
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        $this->user = $user;
        $this->role = $roles[array_rand($roles)];
        $this->project = $project;
        $this->projectDefinition = $projectDefinition;
        $this->entryGenerator = new EntryGenerator($projectDefinition);

        //set storage (and all disks) to local
        config([
            'filesystems.default' => 'local',
            'filesystems.disks.temp.driver' => 'local',
            'filesystems.disks.temp.root' => storage_path('app/temp'),
            'filesystems.disks.entry_original.driver' => 'local',
            'filesystems.disks.entry_original.root' => storage_path('app/entries/photo/entry_original'),
            'filesystems.disks.entry_thumb.driver' => 'local',
            'filesystems.disks.entry_thumb.root' => storage_path('app/entries/photo/entry_thumb'),
            'filesystems.disks.project_thumb.driver' => 'local',
            'filesystems.disks.project_thumb.root' => storage_path('app/projects/project_thumb'),
            'filesystems.disks.project_mobile_logo.driver' => 'local',
            'filesystems.disks.project_mobile_logo.root' => storage_path('app/projects/project_mobile_logo'),
            'filesystems.disks.audio.driver' => 'local',
            'filesystems.disks.audio.root' => storage_path('app/entries/audio'),
            'filesystems.disks.video.driver' => 'local',
            'filesystems.disks.video.root' => storage_path('app/entries/video')
        ]);
    }

    #[DataProvider('multipleRunProvider')] public function test_should_give_private_project_error()
    {
        $this->project->access = config('epicollect.strings.project_access.private');
        $this->project->save();
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug);
        $response->assertStatus(404)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_77",
                        "title" => "This project is private. Please log in.",
                        "source" => "middleware"
                    ]
                ]
            ]);
    }

    //assert getMedia

    #[DataProvider('multipleRunProvider')] public function test_missing_params_in_request()
    {
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug);
        $response->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "type"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "format"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_wrong_type_in_request()
    {
        $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=wrong&name=filename&format=entry_thumb')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_29",
                            "title" => "Value invalid.",
                            "source" => "type"
                        ]

                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_missing_params_in_photo_request()
    {
        $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=photo')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "format"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_missing_name_in_photo_entry_original_request()
    {
        $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=photo&format=entry_original')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_missing_name_in_photo_entry_thumb_request()
    {
        $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=photo&format=entry_thumb')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_missing_params_in_audio_request()
    {
        $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=audio')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "format"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_audio_file_not_found()
    {
        $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=audio&name=ciao&format=audio')
            ->assertStatus(404)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_69",
                            "title" => "No File Uploaded.",
                            "source" => "api-media-controller"
                        ],
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_video_file_not_found()
    {
        $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=audio&name=ciao&format=video')
            ->assertStatus(404)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_69",
                            "title" => "No File Uploaded.",
                            "source" => "api-media-controller"
                        ],
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_missing_params_in_video_request()
    {
        $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=video')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "format"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_photo_placeholder_is_returned()
    {
        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=ciao&format=entry_original')
            ->assertStatus(200);

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $image = Image::read($imageContent);

        $this->assertEquals($image->width(), config('epicollect.media.photo_placeholder.width'));
        $this->assertEquals($image->height(), config('epicollect.media.photo_placeholder.width'));

        // Get image size in bytes
        $imageSizeInBytes = strlen($imageContent);
        $this->assertEquals($imageSizeInBytes, config('epicollect.media.photo_placeholder.size_in_bytes'));

        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
    }

    #[DataProvider('multipleRunProvider')]
    public function test_photo_file_is_returned_landscape()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.jpg';

        //create a fake photo for the entry
        $landscapeWidth = config('epicollect.media.entry_original_landscape')[0];
        $landscapeHeight = config('epicollect.media.entry_original_landscape')[1];
        $image = Image::create($landscapeWidth, $landscapeHeight); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode(new JpegEncoder(50));
        $relativePath = $this->project->ref . '/' . $filename;
        Storage::disk('entry_original')->put($this->project->ref . '/' . $filename, $imageData);
        $this->assertTrue(Storage::disk('entry_original')->exists($relativePath), "File was not created at: $relativePath");


        $thumbWidth = config('epicollect.media.entry_thumb')[0];
        $thumbHeight = config('epicollect.media.entry_thumb')[1];
        $thumb = Image::create($thumbWidth, $thumbHeight); // Width, height, and background color
        // Encode the image as JPEG or other formats
        $thumbData = (string)$thumb->encode(new JpegEncoder(50));
        $relativePath = $this->project->ref . '/' . $filename;
        Storage::disk('entry_thumb')->put($this->project->ref . '/' . $filename, $thumbData);
        $this->assertTrue(Storage::disk('entry_thumb')->exists($relativePath), "File was not created at: $relativePath");

        //entry_original
        $queryString = '?type=photo&name=' . $filename . '&format=entry_original';
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::read($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_landscape')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_landscape')[1]);

        //entry_thumb
        $queryString = '?type=photo&name=' . $filename . '&format=entry_thumb';
        $response = $this->json(
            'GET',
            'api/internal/media/' . $this->project->slug . $queryString
        )
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryThumb = Image::read($imageContent);
        $this->assertEquals($entryThumb->width(), config('epicollect.media.entry_thumb')[0]);
        $this->assertEquals($entryThumb->height(), config('epicollect.media.entry_thumb')[1]);

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($this->project->ref);
        Storage::disk('entry_thumb')->deleteDirectory($this->project->ref);
    }

    #[DataProvider('multipleRunProvider')] public function test_photo_file_is_returned_portrait_size_original()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.jpg';

        //create a fake photo for the entry
        $landscapeWidth = config('epicollect.media.entry_original_portrait')[0];
        $landscapeHeight = config('epicollect.media.entry_original_portrait')[1];
        $image = Image::create($landscapeWidth, $landscapeHeight); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode(new JpegEncoder(50));
        Storage::disk('entry_original')->put($this->project->ref . '/' . $filename, $imageData);

        //entry_original
        $queryString = '?type=photo&name=' . $filename . '&format=entry_original';
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::read($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_portrait')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_portrait')[1]);

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($this->project->ref);
    }

    #[DataProvider('multipleRunProvider')] public function test_photo_file_is_returned_portrait_size_thumb()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        $thumbWidth = config('epicollect.media.entry_thumb')[0];
        $thumbHeight = config('epicollect.media.entry_thumb')[1];
        $thumb = Image::create($thumbWidth, $thumbHeight); // Width, height, and background color
        // Encode the image as JPEG or other formats
        $thumbData = (string)$thumb->encode(new JpegEncoder(50));
        $filename = $entry->uuid . '_' . time() . '.jpg';
        Storage::disk('entry_thumb')->put($this->project->ref . '/' . $filename, $thumbData);

        //entry_thumb
        $queryString = '?type=photo&name=' . $filename . '&format=entry_thumb';
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryThumb = Image::read($imageContent);
        $this->assertEquals($entryThumb->width(), config('epicollect.media.entry_thumb')[0]);
        $this->assertEquals($entryThumb->height(), config('epicollect.media.entry_thumb')[1]);

        //delete fake files
        Storage::disk('entry_thumb')->deleteDirectory($this->project->ref);
    }


    #[DataProvider('multipleRunProvider')]
    public function test_audio_file_is_returned_using_streamed_response()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        //create a fake audio for the entry
        $filename = $entry->uuid . '_' . time() . '.mp4';
        Storage::disk('audio')->put($this->project->ref . '/' . $filename, str_repeat('a', 1000000));
        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=audio&name=' . $filename . '&format=audio';

        $response = $this->withHeaders([
            'Range' => 'bytes=0-10'
        ])->get('api/internal/media/' . $this->project->slug . $queryString);

        // Assert the response is a partial response
        $response->assertStatus(206);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.audio'));
        //delete fake files
        Storage::disk('audio')->deleteDirectory($this->project->ref);
    }

    #[DataProvider('multipleRunProvider')] public function test_video_file_is_returned_using_streamed_response()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.mp4';

        //create a fake photo for the entry
        Storage::disk('video')->put($this->project->ref . '/' . $filename, str_repeat('a', 1000000));

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=video&name=' . $filename . '&format=video';

        $response = $this->withHeaders([
            'Range' => 'bytes=0-10'
        ])->get('api/internal/media/' . $this->project->slug . $queryString);
        // Assert the response is a partial response
        $response->assertStatus(206);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.video'));
        //delete fake files
        Storage::disk('audio')->deleteDirectory($this->project->ref);
    }

    //assert getTempMedia method

    #[DataProvider('multipleRunProvider')] public function test_missing_params_in_request_temp()
    {
        $this->json('GET', 'api/internal/temp-media/' . $this->project->slug)
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "type"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "format"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_wrong_type_in_request_temp()
    {
        $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=wrong&name=filename&format=entry_thumb')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_29",
                            "title" => "Value invalid.",
                            "source" => "type"
                        ]

                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_missing_params_in_photo_request_temp()
    {
        $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=photo')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "format"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_missing_name_in_photo_entry_original_request_temp()
    {
        $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=photo&format=entry_original')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')]
    public function test_missing_name_in_photo_entry_thumb_request_temp()
    {
        $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=photo&format=entry_thumb')
             ->assertStatus(400)
             ->assertJsonStructure([
                 'errors' => [
                     '*' => [
                         'code',
                         'title',
                         'source',
                     ]
                 ]
             ])
             ->assertExactJson(
                 [
                     "errors" => [
                         [
                             "code" => "ec5_21",
                             "title" => "Required field is missing.",
                             "source" => "name"
                         ]
                     ]
                 ]
             );
    }

    #[DataProvider('multipleRunProvider')] public function test_missing_params_in_audio_request_temp()
    {
        $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=audio')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "format"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_audio_file_not_found_temp()
    {
        $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=audio&name=ciao&format=audio')
            ->assertStatus(404)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_69",
                            "title" => "No File Uploaded.",
                            "source" => "api-media-controller"
                        ],
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_video_file_not_found_temp()
    {
        $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=audio&name=ciao&format=video')
            ->assertStatus(404)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_69",
                            "title" => "No File Uploaded.",
                            "source" => "api-media-controller"
                        ],
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')] public function test_missing_params_in_video_request_temp()
    {
        $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=video')
            ->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    '*' => [
                        'code',
                        'title',
                        'source',
                    ]
                ]
            ])
            ->assertExactJson(
                [
                    "errors" => [
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "name"
                        ],
                        [
                            "code" => "ec5_21",
                            "title" => "Required field is missing.",
                            "source" => "format"
                        ]
                    ]
                ]
            );
    }

    #[DataProvider('multipleRunProvider')]
    public function test_photo_placeholder_is_returned_temp()
    {
        $response = $this->get('api/internal/temp-media/' . $this->project->slug . '?type=photo&name=ciao&format=entry_original')
            ->assertStatus(200);

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $image = Image::read($imageContent);

        $this->assertEquals($image->width(), config('epicollect.media.photo_placeholder.width'));
        $this->assertEquals($image->height(), config('epicollect.media.photo_placeholder.width'));

        // Get image size in bytes
        $imageSizeInBytes = strlen($imageContent);
        $this->assertEquals($imageSizeInBytes, config('epicollect.media.photo_placeholder.size_in_bytes'));

        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
    }

    #[DataProvider('multipleRunProvider')]
    public function test_photo_file_is_returned_landscape_temp()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.jpg';

        //create a fake photo for the entry
        $landscapeWidth = config('epicollect.media.entry_original_landscape')[0];
        $landscapeHeight = config('epicollect.media.entry_original_landscape')[1];
        $image = Image::create($landscapeWidth, $landscapeHeight); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode(new JpegEncoder(50));
        Storage::disk('temp')->put('photo/' . $this->project->ref . '/' . $filename, $imageData);

        //entry_original
        $queryString = '?type=photo&name=' . $filename . '&format=entry_original';
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::read($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_landscape')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_landscape')[1]);

        //delete fake files
        Storage::disk('temp')->deleteDirectory('photo/' . $this->project->ref);
    }

    #[DataProvider('multipleRunProvider')] public function test_photo_file_is_returned_portrait_temp()
    {
        //create project
        factory(Project::class)->create(
            ['access' => config('epicollect.strings.project_access.public')]
        );

        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.jpg';

        //create a fake photo for the entry
        $landscapeWidth = config('epicollect.media.entry_original_portrait')[0];
        $landscapeHeight = config('epicollect.media.entry_original_portrait')[1];
        $image = Image::create($landscapeWidth, $landscapeHeight); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode(new JpegEncoder(50));
        Storage::disk('temp')->put('photo/' . $this->project->ref . '/' . $filename, $imageData);


        //entry_original
        $queryString = '?type=photo&name=' . $filename . '&format=entry_original';
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::read($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_portrait')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_portrait')[1]);


        //delete fake files
        Storage::disk('temp')->deleteDirectory('photo/' . $this->project->ref);
    }

    #[DataProvider('multipleRunProvider')] public function test_audio_file_is_returned_using_streamed_response_temp()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.mp4';

        //create a fake audio of 2KB
        Storage::disk('temp')->put('audio/' . $this->project->ref . '/' . $filename, str_repeat('A', 2048));

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=audio&name=' . $filename . '&format=audio';

        $response = $this->withHeaders([
            'Range' => 'bytes=0-10'
        ])->get('api/internal/temp-media/' . $this->project->slug . $queryString);

        $response->assertStatus(206);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.audio'));
        //delete fake files
        Storage::disk('temp')->deleteDirectory('audio/' . $this->project->ref);
    }

    #[DataProvider('multipleRunProvider')] public function test_video_file_is_returned_using_streamed_response_temp()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.mp4';

        //create a fake video of 2KB
        Storage::disk('temp')->put('video/' . $this->project->ref . '/' . $filename, str_repeat('A', 2048));

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=video&name=' . $filename . '&format=video';

        $response = $this->withHeaders([
            'Range' => 'bytes=0-10'
        ])->get('api/internal/temp-media/' . $this->project->slug . $queryString);

        $response->assertStatus(206);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.video'));
        //delete fake files
        Storage::disk('temp')->deleteDirectory('video/' . $this->project->ref);
    }

    /**
     * @throws Throwable
     */
    public function test_parent_entry_audio_is_playable()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );


        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );

        //add fake audio to the entry, with 2KB size to make the range header work
        $entryUuid = $entryPayload['data']['id'];
        $audioFilename = $entryUuid. '_' . time() . '.mp4';
        Storage::disk('audio')->put($this->project->ref . '/' . $audioFilename, str_repeat('A', 2048));

        //try to get the audio file using a range request to get 206 response
        $queryString = '?type=audio&name=' . $audioFilename . '&format=audio';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->withHeaders([
                    'Range' => 'bytes=0-10'
                ])
                ->get('api/internal/media/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(206);

            //now remove all the leftover fake files
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_parent_entry_audio_is_downloadable()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );


        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );

        //add fake audio to the entry, with 2KB size
        $entryUuid = $entryPayload['data']['id'];
        $audioFilename = $entryUuid. '_' . time() . '.mp4';
        $audioContent = str_repeat('A', 2048);
        Storage::disk('audio')->put($this->project->ref . '/' . $audioFilename, $audioContent);

        //try to get the audio file
        $queryString = '?type=audio&name=' . $audioFilename . '&format=audio';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/media/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            // Assert headers
            $response[0]->assertHeader('Content-Type', 'audio/mp4');
            $response[0]->assertHeader('Content-Length', (string) strlen($audioContent));
            $response[0]->assertHeader('Accept-Ranges', 'bytes');

            //now remove all the leftover fake files
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_parent_entry_video_is_playable()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );


        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );

        //add fake video to the entry, with 2KB size to make the range header work
        $entryUuid = $entryPayload['data']['id'];
        $videoFilename = $entryUuid. '_' . time() . '.mp4';
        Storage::disk('video')->put($this->project->ref . '/' . $videoFilename, str_repeat('A', 2048));

        //try to get the video file using a range request to get 206 response
        $queryString = '?type=video&name=' . $videoFilename . '&format=video';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->withHeaders([
                    'Range' => 'bytes=0-10'
                ])
                ->get('api/internal/media/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(206);

            //now remove all the leftover fake files
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_parent_entry_video_is_downloadable()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );


        $this->assertEntryRowAgainstPayload(
            $entryRowBundle,
            $entryPayload
        );

        //add fake video to the entry, with 2KB size
        $entryUuid = $entryPayload['data']['id'];
        $videoFilename = $entryUuid. '_' . time() . '.mp4';
        $videoContent = str_repeat('A', 2048);
        Storage::disk('video')->put($this->project->ref . '/' . $videoFilename, $videoContent);

        //try to get the video file
        $queryString = '?type=video&name=' . $videoFilename . '&format=video';
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/media/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            // Assert headers
            $response[0]->assertHeader('Content-Type', 'video/mp4');
            $response[0]->assertHeader('Content-Length', (string) strlen($videoContent));
            $response[0]->assertHeader('Accept-Ranges', 'bytes');


            //now remove all the leftover fake files
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }
}
