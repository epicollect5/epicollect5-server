<?php

namespace Tests\Http\Controllers\Api\Entries;

use Carbon\Carbon;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStat;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Eloquent\User;
use Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ec5\Traits\Assertions;
use ZipArchive;

class DownloadControllerTest extends TestCase
{
    use DatabaseTransactions, Assertions;

    public function test_should_fails_if_user_not_logged_in()
    {
        //create user
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('ec5Strings.project_access.public')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id
            ]
        );

        factory(ProjectStat::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        $response = $this->get('api/internal/download-entries/' . $project->slug)
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
                            "code" => "ec5_86",
                            "title" => "User not authenticated.",
                            "source" => "download-entries"
                        ],
                    ]
                ]
            );
    }

    public function test_download_json()
    {
        //create user
        $format = 'json';
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('ec5Strings.project_access.public')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id
            ]
        );

        factory(ProjectStat::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $cookies = [config('epicollect.strings.cookies.download-entries') => Carbon::now()->timestamp];
        $params = [
            config('epicollect.strings.cookies.download-entries') => Carbon::now()->timestamp,
            'format' => $format
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);

        $response->assertStatus(200);
        // Assert that the returned file is a zip file
        $this->assertTrue($response->headers->get('Content-Type') === 'application/zip');
        //assert filename
        $zipName = $project->slug . '-' . $params['format'] . '.zip';
        // Get the Content-Disposition header
        $contentDisposition = $response->headers->get('Content-Disposition');
        // Extract the filename from the header
        preg_match('/filename="(.+)"/', $contentDisposition, $matches);
        $extractedFilename = $matches[1] ?? null;
        $this->assertEquals($zipName, $extractedFilename);
        // Get the response content as a file
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format);

        Storage::delete($filePath);
    }

    public function test_error_response_with_wrong_params()
    {
        //create user
        $format = 'json';
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('ec5Strings.project_access.public')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id
            ]
        );

        factory(ProjectStat::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $cookies = [config('epicollect.strings.cookies.download-entries') => Carbon::now()->timestamp];
        $params = [
            config('epicollect.strings.cookies.download-entries') => Carbon::now()->timestamp,
            'format' => 'ciao',
            'map_index' => 0
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);
        $response->assertStatus(400);

        $params = [
            config('epicollect.strings.cookies.download-entries') => Carbon::now()->timestamp,
            'format' => 'gibberish',
            'map_index' => 0
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);
        $response->assertStatus(400);

        $params = [
            config('epicollect.strings.cookies.download-entries') => Carbon::now()->timestamp,
            'format' => 'csv',
            'map_index' => 'gibberish'
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);
        $response->assertStatus(400);

    }

    public function test_download_csv()
    {
        //create user
        $format = 'csv';
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('ec5Strings.project_access.public')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id
            ]
        );

        factory(ProjectStat::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $cookies = [config('epicollect.strings.cookies.download-entries') => Carbon::now()->timestamp];
        $params = [
            config('epicollect.strings.cookies.download-entries') => Carbon::now()->timestamp,
            'format' => $format
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);

        $response->assertStatus(200);
        // Assert that the returned file is a zip file
        $this->assertTrue($response->headers->get('Content-Type') === 'application/zip');
        //assert filename
        $zipName = $project->slug . '-' . $params['format'] . '.zip';
        // Get the Content-Disposition header
        $contentDisposition = $response->headers->get('Content-Disposition');
        // Extract the filename from the header
        preg_match('/filename="(.+)"/', $contentDisposition, $matches);
        $extractedFilename = $matches[1] ?? null;
        $this->assertEquals($zipName, $extractedFilename);
        // Get the response content as a file
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format);

        Storage::delete($filePath);
    }

    public function test_should_abort_if_timestamp_missing()
    {
        //create user
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('ec5Strings.project_access.public')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id
            ]
        );

        factory(ProjectStat::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        $response = $this->actingAs($user)->get('api/internal/download-entries/' . $project->slug)
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
                            "code" => "ec5_29",
                            "title" => "Value invalid.",
                            "source" => "download-entries"
                        ],
                    ]
                ]
            );
    }

    public function test_should_abort_if_timestamp_malformed()
    {
        //create user
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('ec5Strings.project_access.public')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id
            ]
        );

        factory(ProjectStat::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $response = $this
            ->actingAs($user)
            ->json('GET', 'api/internal/download-entries/' . $project->slug)
            ->withCookie(config('epicollect.strings.cookies.download-entries'), 'gibberish');


        //for reasons unknown when using withCookie() we need to change approch to test the response
        $this->assertEquals(404, $response->getStatusCode());

        $jsonContent = json_decode($response->getContent(), true);
        $this->assertIsArray($jsonContent);

        // Define the expected structure
        $expectedContent = [
            "errors" => [
                [
                    "code" => "ec5_29",
                    "title" => "Value invalid.",
                    "source" => "download-entries",
                ],
            ],
        ];

        // Assert that the decoded JSON content matches the expected structure exactly
        $this->assertEquals($expectedContent, $jsonContent);
    }

    private function assertZipContent($filePath, $extension)
    {
        $zip = new ZipArchive();
        $zip->open($filePath);
        $fileFound = false;
        // Check each file in the zip archive
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $extractedFilename = $fileInfo['name'];
            // Check if the file has a .json extension
            if (pathinfo($extractedFilename, PATHINFO_EXTENSION) === $extension) {
                $fileFound = true;
                // Extract the file content and perform assertions
                $content = $zip->getFromIndex($i);
                // Asserts on the content or other validations
                $this->assertNotEmpty($content);
                // Additional assertions on content can be done here
            }
        }
        // Close the zip file
        $zip->close();
        // Assert that at least one JSON file was found in the zip
        $this->assertTrue($fileFound);
    }


}