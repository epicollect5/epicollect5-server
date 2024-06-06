<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Image;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    private $user;
    private $project;

    use DatabaseTransactions;

    public function setUp()
    {
        parent::setUp();

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
        $this->project = $project;
    }

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_should_give_private_project_error()
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_params_in_request()
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_wrong_type_in_request()
    {
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=wrong&name=filename&format=entry_thumb')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_params_in_photo_request()
    {
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=photo')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_name_in_photo_entry_original_request()
    {
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=photo&format=entry_original')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_name_in_photo_entry_thumb_request()
    {
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=photo&format=entry_thumb')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_params_in_audio_request()
    {
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=audio')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_audio_file_not_found()
    {
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=audio&name=ciao&format=audio')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_video_file_not_found()
    {
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=audio&name=ciao&format=video')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_params_in_video_request()
    {
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . '?type=video')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_photo_placeholder_is_returned()
    {
        $response = $this->get('api/internal/media/' . $this->project->slug . '?type=photo&name=ciao&format=entry_original')
            ->assertStatus(200);

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $image = Image::make($imageContent);

        $this->assertEquals($image->width(), config('epicollect.media.photo_placeholder.width'));
        $this->assertEquals($image->height(), config('epicollect.media.photo_placeholder.width'));

        // Get image size in bytes
        $imageSizeInBytes = strlen($imageContent);
        $this->assertEquals($imageSizeInBytes, config('epicollect.media.photo_placeholder.size_in_bytes'));

        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
    }

    /**
     * @dataProvider multipleRunProvider
     */
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
        $image = Image::canvas($landscapeWidth, $landscapeHeight, '#ffffff'); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode('jpg');
        Storage::disk('entry_original')->put($this->project->ref . '/' . $filename, $imageData);

        $thumbWidth = config('epicollect.media.entry_thumb')[0];
        $thumbHeight = config('epicollect.media.entry_thumb')[1];
        $thumb = Image::canvas($thumbWidth, $thumbHeight, '#ffffff'); // Width, height, and background color
        // Encode the image as JPEG or other formats
        $thumbData = (string)$thumb->encode('jpg');
        Storage::disk('entry_thumb')->put($this->project->ref . '/' . $filename, $thumbData);

        //entry_original
        $queryString = '?type=photo&name=' . $filename . '&format=entry_original';
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::make($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_landscape')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_landscape')[1]);

        //entry_thumb
        $queryString = '?type=photo&name=' . $filename . '&format=entry_thumb';
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryThumb = Image::make($imageContent);
        $this->assertEquals($entryThumb->width(), config('epicollect.media.entry_thumb')[0]);
        $this->assertEquals($entryThumb->height(), config('epicollect.media.entry_thumb')[1]);

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($this->project->ref);
        Storage::disk('entry_thumb')->deleteDirectory($this->project->ref);
    }

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_photo_file_is_returned_portrait_size_original()
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
        $image = Image::canvas($landscapeWidth, $landscapeHeight, '#ffffff'); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode('jpg');
        Storage::disk('entry_original')->put($this->project->ref . '/' . $filename, $imageData);

        //entry_original
        $queryString = '?type=photo&name=' . $filename . '&format=entry_original';
        $response = $this->json('GET', 'api/internal/media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::make($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_portrait')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_portrait')[1]);

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($this->project->ref);
    }

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_photo_file_is_returned_portrait_size_thumb()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.jpg';

        //create a fake photo for the entry
        $thumbWidth = config('epicollect.media.entry_thumb')[0];
        $thumbHeight = config('epicollect.media.entry_thumb')[1];
        $thumb = Image::canvas($thumbWidth, $thumbHeight, '#ffffff'); // Width, height, and background color
        // Encode the image as JPEG or other formats
        $thumbData = (string)$thumb->encode('jpg');
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
        $entryThumb = Image::make($imageContent);
        $this->assertEquals($entryThumb->width(), config('epicollect.media.entry_thumb')[0]);
        $this->assertEquals($entryThumb->height(), config('epicollect.media.entry_thumb')[1]);

        //delete fake files
        Storage::disk('entry_thumb')->deleteDirectory($this->project->ref);
    }


    /**
     * @dataProvider multipleRunProvider
     */
    public function test_audio_file_is_returned_using_streamed_response()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        $filename = $entry->uuid . '_' . time() . '.mp4';
        Storage::disk('audio')->put($this->project->ref . '/' . $filename, '');
        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=audio&name=' . $filename . '&format=audio';

        $response = $this->get('api/internal/media/' . $this->project->slug . $queryString);
        //this is needed to close the streaming response
        //https://stackoverflow.com/questions/38400305/phpunit-help-needed-about-risky-tests
        ob_start();

        $response->assertStatus(200);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.audio'));
        //delete fake files
        Storage::disk('audio')->deleteDirectory($this->project->ref);
    }

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_video_file_is_returned_using_streamed_response()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.mp4';

        //create a fake photo for the entry
        Storage::disk('video')->put($this->project->ref . '/' . $filename, '');

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=video&name=' . $filename . '&format=video';

        $response = $this->get('api/internal/media/' . $this->project->slug . $queryString);
        //this is needed to close the streaming response
        //https://stackoverflow.com/questions/38400305/phpunit-help-needed-about-risky-tests
        ob_start();

        $response->assertStatus(200);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.video'));
        //delete fake files
        Storage::disk('audio')->deleteDirectory($this->project->ref);
    }

    //assert getTempMedia method

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_params_in_request_temp()
    {
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug)
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_wrong_type_in_request_temp()
    {
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=wrong&name=filename&format=entry_thumb')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_params_in_photo_request_temp()
    {
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=photo')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_name_in_photo_entry_original_request_temp()
    {
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=photo&format=entry_original')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_name_in_photo_entry_thumb_request_temp()
    {
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=photo&format=entry_thumb')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_params_in_audio_request_temp()
    {
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=audio')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_audio_file_not_found_temp()
    {
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=audio&name=ciao&format=audio')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_video_file_not_found_temp()
    {
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=audio&name=ciao&format=video')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_missing_params_in_video_request_temp()
    {
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . '?type=video')
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
            ->assertExactJson([
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

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_photo_placeholder_is_returned_temp()
    {
        $response = $this->get('api/internal/temp-media/' . $this->project->slug . '?type=photo&name=ciao&format=entry_original')
            ->assertStatus(200);

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $image = Image::make($imageContent);

        $this->assertEquals($image->width(), config('epicollect.media.photo_placeholder.width'));
        $this->assertEquals($image->height(), config('epicollect.media.photo_placeholder.width'));

        // Get image size in bytes
        $imageSizeInBytes = strlen($imageContent);
        $this->assertEquals($imageSizeInBytes, config('epicollect.media.photo_placeholder.size_in_bytes'));

        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));
    }

    /**
     * @dataProvider multipleRunProvider
     */
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
        $image = Image::canvas($landscapeWidth, $landscapeHeight, '#ffffff'); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode('jpg');
        Storage::disk('temp')->put('photo/' . $this->project->ref . '/' . $filename, $imageData);

        //entry_original
        $queryString = '?type=photo&name=' . $filename . '&format=entry_original';
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::make($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_landscape')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_landscape')[1]);

        //delete fake files
        Storage::disk('temp')->deleteDirectory('photo/' . $this->project->ref);
    }

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_photo_file_is_returned_portrait_temp()
    {
        //create project
        $project = factory(Project::class)->create(
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
        $image = Image::canvas($landscapeWidth, $landscapeHeight, '#ffffff'); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode('jpg');
        Storage::disk('temp')->put('photo/' . $this->project->ref . '/' . $filename, $imageData);


        //entry_original
        $queryString = '?type=photo&name=' . $filename . '&format=entry_original';
        $response = $this->json('GET', 'api/internal/temp-media/' . $this->project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::make($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_portrait')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_portrait')[1]);


        //delete fake files
        Storage::disk('temp')->deleteDirectory('photo/' . $this->project->ref);
    }

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_audio_file_is_returned_using_streamed_response_temp()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.mp4';

        //create a fake photo for the entry
        Storage::disk('temp')->put('audio/' . $this->project->ref . '/' . $filename, '');

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=audio&name=' . $filename . '&format=audio';

        $response = $this->get('api/internal/temp-media/' . $this->project->slug . $queryString);
        //this is needed to close the streaming response (when testing it)
        //https://stackoverflow.com/questions/38400305/phpunit-help-needed-about-risky-tests
        //  ob_start();

        $response->assertStatus(200);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.audio'));
        //delete fake files
        Storage::disk('temp')->deleteDirectory('audio/' . $this->project->ref);
    }

    /**
     * @dataProvider multipleRunProvider
     */
    public function test_video_file_is_returned_using_streamed_response_temp()
    {
        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->project->ref . '_' . uniqid()
        ]);

        $filename = $entry->uuid . '_' . time() . '.mp4';

        //create a fake photo for the entry
        Storage::disk('temp')->put('video/' . $this->project->ref . '/' . $filename, '');

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=video&name=' . $filename . '&format=video';

        $response = $this->get('api/internal/temp-media/' . $this->project->slug . $queryString);
        //this is needed to close the streaming response (when testing it)
        //https://stackoverflow.com/questions/38400305/phpunit-help-needed-about-risky-tests
        //  ob_start();

        $response->assertStatus(200);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.video'));
        //delete fake files
        Storage::disk('temp')->deleteDirectory('video/' . $this->project->ref);
    }
}
