<?php

namespace Http\Controllers\Api\Project;

use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\Project;
use Illuminate\Support\Facades\Storage;
use Image;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MediaControllerTest extends TestCase
{
    use DatabaseTransactions;

    //assert getMedia
    public function test_missing_params_in_request()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/media/' . $project->slug)
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

    public function test_wrong_type_in_request()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/media/' . $project->slug . '?type=wrong&name=filename&format=entry_thumb')
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

    public function test_missing_params_in_photo_request()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/media/' . $project->slug . '?type=photo')
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

    public function test_missing_name_in_photo_entry_original_request()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/media/' . $project->slug . '?type=photo&format=entry_original')
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

    public function test_missing_name_in_photo_entry_thumb_request()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/media/' . $project->slug . '?type=photo&format=entry_thumb')
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

    public function test_missing_params_in_audio_request()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/media/' . $project->slug . '?type=audio')
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

    public function test_audio_file_not_found()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/media/' . $project->slug . '?type=audio&name=ciao&format=audio')
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

    public function test_video_file_not_found()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/media/' . $project->slug . '?type=audio&name=ciao&format=video')
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

    public function test_missing_params_in_video_request()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/media/' . $project->slug . '?type=video')
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

    public function test_photo_placeholder_is_returned()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->get('api/internal/media/' . $project->slug . '?type=photo&name=ciao&format=entry_original')
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

    public function test_photo_file_is_returned_landscape()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        $landscapeWidth = config('epicollect.media.entry_original_landscape')[0];
        $landscapeHeight = config('epicollect.media.entry_original_landscape')[1];
        $image = Image::canvas($landscapeWidth, $landscapeHeight, '#ffffff'); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode('jpg');
        Storage::disk('entry_original')->put($project->ref . '/' . $entry->uuid . '.jpg', $imageData);

        $thumbWidth = config('epicollect.media.entry_thumb')[0];
        $thumbHeight = config('epicollect.media.entry_thumb')[1];
        $thumb = Image::canvas($thumbWidth, $thumbHeight, '#ffffff'); // Width, height, and background color
        // Encode the image as JPEG or other formats
        $thumbData = (string)$thumb->encode('jpg');
        Storage::disk('entry_thumb')->put($project->ref . '/' . $entry->uuid . '.jpg', $thumbData);

        //entry_original
        $queryString = '?type=photo&name=' . $entry->uuid . '.jpg&format=entry_original';
        $response = $this->json('GET', 'api/internal/media/' . $project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::make($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_landscape')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_landscape')[1]);

        //entry_thumb
        $queryString = '?type=photo&name=' . $entry->uuid . '.jpg&format=entry_thumb';
        $response = $this->json('GET', 'api/internal/media/' . $project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryThumb = Image::make($imageContent);
        $this->assertEquals($entryThumb->width(), config('epicollect.media.entry_thumb')[0]);
        $this->assertEquals($entryThumb->height(), config('epicollect.media.entry_thumb')[1]);

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($project->ref);
        Storage::disk('entry_thumb')->deleteDirectory($project->ref);
    }

    public function test_photo_file_is_returned_portrait()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        $landscapeWidth = config('epicollect.media.entry_original_portrait')[0];
        $landscapeHeight = config('epicollect.media.entry_original_portrait')[1];
        $image = Image::canvas($landscapeWidth, $landscapeHeight, '#ffffff'); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode('jpg');
        Storage::disk('entry_original')->put($project->ref . '/' . $entry->uuid . '.jpg', $imageData);

        $thumbWidth = config('epicollect.media.entry_thumb')[0];
        $thumbHeight = config('epicollect.media.entry_thumb')[1];
        $thumb = Image::canvas($thumbWidth, $thumbHeight, '#ffffff'); // Width, height, and background color
        // Encode the image as JPEG or other formats
        $thumbData = (string)$thumb->encode('jpg');
        Storage::disk('entry_thumb')->put($project->ref . '/' . $entry->uuid . '.jpg', $thumbData);

        //entry_original
        $queryString = '?type=photo&name=' . $entry->uuid . '.jpg&format=entry_original';
        $response = $this->json('GET', 'api/internal/media/' . $project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::make($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_portrait')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_portrait')[1]);

        //entry_thumb
        $queryString = '?type=photo&name=' . $entry->uuid . '.jpg&format=entry_thumb';
        $response = $this->json('GET', 'api/internal/media/' . $project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));

        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryThumb = Image::make($imageContent);
        $this->assertEquals($entryThumb->width(), config('epicollect.media.entry_thumb')[0]);
        $this->assertEquals($entryThumb->height(), config('epicollect.media.entry_thumb')[1]);

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($project->ref);
        Storage::disk('entry_thumb')->deleteDirectory($project->ref);
    }


    public function test_audio_file_is_returned_using_streamed_response()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        Storage::disk('audio')->put($project->ref . '/' . $entry->uuid . '.mp4', '');

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=audio&name=' . $entry->uuid . '.mp4&format=audio';

        $response = $this->get('api/internal/media/' . $project->slug . $queryString);
        //this is needed to close the streaming response
        //https://stackoverflow.com/questions/38400305/phpunit-help-needed-about-risky-tests
        ob_start();

        $response->assertStatus(200);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.audio'));
        //delete fake files
        Storage::disk('audio')->deleteDirectory($project->ref);
    }

    public function test_video_file_is_returned_using_streamed_response()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        Storage::disk('video')->put($project->ref . '/' . $entry->uuid . '.mp4', '');

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=video&name=' . $entry->uuid . '.mp4&format=video';

        $response = $this->get('api/internal/media/' . $project->slug . $queryString);
        //this is needed to close the streaming response
        //https://stackoverflow.com/questions/38400305/phpunit-help-needed-about-risky-tests
        ob_start();

        $response->assertStatus(200);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.video'));
        //delete fake files
        Storage::disk('audio')->deleteDirectory($project->ref);
    }

    //assert getTempMedia method
    public function test_missing_params_in_request_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug)
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

    public function test_wrong_type_in_request_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . '?type=wrong&name=filename&format=entry_thumb')
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

    public function test_missing_params_in_photo_request_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . '?type=photo')
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

    public function test_missing_name_in_photo_entry_original_request_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . '?type=photo&format=entry_original')
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

    public function test_missing_name_in_photo_entry_thumb_request_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . '?type=photo&format=entry_thumb')
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

    public function test_missing_params_in_audio_request_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . '?type=audio')
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

    public function test_audio_file_not_found_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . '?type=audio&name=ciao&format=audio')
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

    public function test_video_file_not_found_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . '?type=audio&name=ciao&format=video')
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

    public function test_missing_params_in_video_request_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . '?type=video')
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

    public function test_photo_placeholder_is_returned_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        $response = $this->get('api/internal/temp-media/' . $project->slug . '?type=photo&name=ciao&format=entry_original')
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

    public function test_photo_file_is_returned_landscape_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        $landscapeWidth = config('epicollect.media.entry_original_landscape')[0];
        $landscapeHeight = config('epicollect.media.entry_original_landscape')[1];
        $image = Image::canvas($landscapeWidth, $landscapeHeight, '#ffffff'); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode('jpg');
        Storage::disk('temp')->put('photo/' . $project->ref . '/' . $entry->uuid . '.jpg', $imageData);

        //entry_original
        $queryString = '?type=photo&name=' . $entry->uuid . '.jpg&format=entry_original';
        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::make($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_landscape')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_landscape')[1]);

        //delete fake files
        Storage::disk('temp')->deleteDirectory('photo/' . $project->ref);
    }

    public function test_photo_file_is_returned_portrait_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        $landscapeWidth = config('epicollect.media.entry_original_portrait')[0];
        $landscapeHeight = config('epicollect.media.entry_original_portrait')[1];
        $image = Image::canvas($landscapeWidth, $landscapeHeight, '#ffffff'); // Width, height, and background color

        // Encode the image as JPEG or other formats
        $imageData = (string)$image->encode('jpg');
        Storage::disk('temp')->put('photo/' . $project->ref . '/' . $entry->uuid . '.jpg', $imageData);


        //entry_original
        $queryString = '?type=photo&name=' . $entry->uuid . '.jpg&format=entry_original';
        $response = $this->json('GET', 'api/internal/temp-media/' . $project->slug . $queryString)
            ->assertStatus(200);
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.photo'));


        // Get the image content from the response
        $imageContent = $response->getContent();
        // Create an Intervention Image instance from the image content
        $entryOriginal = Image::make($imageContent);
        $this->assertEquals($entryOriginal->width(), config('epicollect.media.entry_original_portrait')[0]);
        $this->assertEquals($entryOriginal->height(), config('epicollect.media.entry_original_portrait')[1]);


        //delete fake files
        Storage::disk('temp')->deleteDirectory('photo/' . $project->ref);
    }

    public function test_audio_file_is_returned_using_streamed_response_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        Storage::disk('temp')->put('audio/' . $project->ref . '/' . $entry->uuid . '.mp4', '');

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=audio&name=' . $entry->uuid . '.mp4&format=audio';

        $response = $this->get('api/internal/temp-media/' . $project->slug . $queryString);
        //this is needed to close the streaming response (when testing it)
        //https://stackoverflow.com/questions/38400305/phpunit-help-needed-about-risky-tests
        //  ob_start();

        $response->assertStatus(200);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.audio'));
        //delete fake files
        Storage::disk('temp')->deleteDirectory('audio/' . $project->ref);
    }

    public function test_video_file_is_returned_using_streamed_response_temp()
    {
        //create project
        $project = factory(Project::class)->create(
            ['access' => config('ec5Strings.project_access.public')]
        );

        //create a fake entry
        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid()
        ]);

        //create a fake photo for the entry
        Storage::disk('temp')->put('video/' . $project->ref . '/' . $entry->uuid . '.mp4', '');

        //audio in streaming (206 partial, not sure how to test it in PHPUnit)
        $queryString = '?type=video&name=' . $entry->uuid . '.mp4&format=video';

        $response = $this->get('api/internal/temp-media/' . $project->slug . $queryString);
        //this is needed to close the streaming response (when testing it)
        //https://stackoverflow.com/questions/38400305/phpunit-help-needed-about-risky-tests
        //  ob_start();

        $response->assertStatus(200);//
        $response->assertHeader('Content-Type', config('epicollect.media.content_type.video'));
        //delete fake files
        Storage::disk('temp')->deleteDirectory('video/' . $project->ref);
    }

}
