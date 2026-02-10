<?php

namespace Tests\Http\Controllers\Api\Entries\Download;

use Auth;
use Carbon\Carbon;
use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Storage;
use Tests\TestCase;
use Throwable;
use ZipArchive;

class DownloadControllerTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    public function test_should_fails_if_user_not_logged_in()
    {
        //create user
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('epicollect.strings.project_access.public')
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

        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        $this->get('api/internal/download-entries/' . $project->slug)
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
                            "code" => "ec5_86",
                            "title" => "User not authenticated.",
                            "source" => "download-entries"
                        ],
                    ]
                ]
            );
    }

    /**
     * @throws Throwable
     */
    public function test_download_json_public()
    {
        //create user
        $format = 'json';
        $user = factory(User::class)->create();
        $projectName = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.name');
        $projectSlug = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.slug');
        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $projectDefinition['data']['project']['name'] = $projectName;
        $projectDefinition['data']['project']['slug'] = $projectSlug;
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.public')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
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

        //generate entries by creator
        $entryGenerator = new EntryGenerator($projectDefinition);
        $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 10; $i++) {
            Auth::login($user);
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $entryGenerator->createParentEntryRow(
                $user,
                $project,
                config('epicollect.strings.project_roles.creator'),
                $projectDefinition,
                $entryPayloads[$i]
            );
            Auth::logout();

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            10,
            Entry::where('project_id', $project->id)->get()
        );

        $cookies = [config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp];
        $params = [
            config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp,
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
        if (preg_match('/filename[^;=\n]*=["\']?([^"\']*)["\']?/', $contentDisposition, $matches)) {
            $extractedFilename = $matches[1];
        } else {
            $extractedFilename = null;
        }
        $this->assertEquals($zipName, $extractedFilename);
        // Get the response content as a file
        /** @noinspection PhpUndefinedMethodInspection */
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $numOfForms = sizeof($projectDefinition['data']['project']['forms']);
        $numOfBranches = 0;
        foreach ($projectDefinition['data']['project']['forms'] as $form) {
            foreach ($form['inputs'] as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                    $numOfBranches++;
                }
            }
        }

        $access = $project->access;
        $this->assertZipContent($filePath, $format, $numOfForms, $numOfBranches, $access);

        Storage::delete($filePath);
        Storage::disk('audio')->deleteDirectory($project->ref);
        Storage::disk('photo')->deleteDirectory($project->ref);
        Storage::disk('video')->deleteDirectory($project->ref);
    }

    public function test_download_json_private()
    {
        //create user
        $format = 'json';
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('epicollect.strings.project_access.private')
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

        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $cookies = [config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp];
        $params = [
            config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp,
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
        if (preg_match('/filename[^;=\n]*=["\']?([^"\']*)["\']?/', $contentDisposition, $matches)) {
            $extractedFilename = $matches[1];
        } else {
            $extractedFilename = null;
        }
        $this->assertEquals($zipName, $extractedFilename);
        // Get the response content as a file
        /** @noinspection PhpUndefinedMethodInspection */
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format, 1, 0);

        Storage::delete($filePath);
    }

    /**
     * @throws Throwable
     */
    public function test_download_csv_private()
    {
        //create user
        $format = 'csv';
        $user = factory(User::class)->create();
        $projectName = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.name');
        $projectSlug = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.slug');
        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $projectDefinition['data']['project']['name'] = $projectName;
        $projectDefinition['data']['project']['slug'] = $projectSlug;
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
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

        //generate entries by creator
        $entryGenerator = new EntryGenerator($projectDefinition);
        $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 10; $i++) {
            Auth::login($user);
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $entryGenerator->createParentEntryRow(
                $user,
                $project,
                config('epicollect.strings.project_roles.creator'),
                $projectDefinition,
                $entryPayloads[$i]
            );
            Auth::logout();

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            10,
            Entry::where('project_id', $project->id)->get()
        );

        $cookies = [config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp];
        $params = [
            config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp,
            'format' => $format
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);

        $response->assertStatus(200);
        // Assert that the returned file is a zip file. If .txt, that is an error response as a file
        $this->assertTrue($response->headers->get('Content-Type') === 'application/zip');
        //assert filename
        $zipName = $project->slug . '-' . $params['format'] . '.zip';
        // Get the Content-Disposition header
        $contentDisposition = $response->headers->get('Content-Disposition');
        // Extract the filename from the header
        if (preg_match('/filename[^;=\n]*=["\']?([^"\']*)["\']?/', $contentDisposition, $matches)) {
            $extractedFilename = $matches[1];
        } else {
            $extractedFilename = null;
        }
        $this->assertEquals($zipName, $extractedFilename);
        // Get the response content as a file
        /** @noinspection PhpUndefinedMethodInspection */
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();


        $numOfForms = sizeof($projectDefinition['data']['project']['forms']);
        $numOfBranches = 0;
        foreach ($projectDefinition['data']['project']['forms'] as $form) {
            foreach ($form['inputs'] as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                    $numOfBranches++;
                }
            }
        }

        $this->assertZipContent($filePath, $format, $numOfForms, $numOfBranches);

        Storage::disk('audio')->deleteDirectory($project->ref);
        Storage::disk('photo')->deleteDirectory($project->ref);
        Storage::disk('video')->deleteDirectory($project->ref);
        Storage::delete($filePath);
    }

    /**
     * @throws Throwable
     */
    public function test_download_csv_public()
    {
        //create user
        $format = 'csv';
        $user = factory(User::class)->create();
        $projectName = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.name');
        $projectSlug = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.slug');
        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $projectDefinition['data']['project']['name'] = $projectName;
        $projectDefinition['data']['project']['slug'] = $projectSlug;
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.public')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
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

        //generate entries by creator
        $entryGenerator = new EntryGenerator($projectDefinition);
        $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 10; $i++) {
            Auth::login($user);
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $entryGenerator->createParentEntryRow(
                $user,
                $project,
                config('epicollect.strings.project_roles.creator'),
                $projectDefinition,
                $entryPayloads[$i]
            );
            Auth::logout();

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            10,
            Entry::where('project_id', $project->id)->get()
        );

        $cookies = [config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp];
        $params = [
            config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp,
            'format' => $format
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);

        $response->assertStatus(200);
        // Assert that the returned file is a zip file. If .txt, that is an error response as a file
        $this->assertTrue($response->headers->get('Content-Type') === 'application/zip');
        //assert filename
        $zipName = $project->slug . '-' . $params['format'] . '.zip';
        // Get the Content-Disposition header
        $contentDisposition = $response->headers->get('Content-Disposition');
        // Extract the filename from the header
        if (preg_match('/filename[^;=\n]*=["\']?([^"\']*)["\']?/', $contentDisposition, $matches)) {
            $extractedFilename = $matches[1];
        } else {
            $extractedFilename = null;
        }
        $this->assertEquals($zipName, $extractedFilename);
        // Get the response content as a file
        /** @noinspection PhpUndefinedMethodInspection */
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();


        $numOfForms = sizeof($projectDefinition['data']['project']['forms']);
        $numOfBranches = 0;
        foreach ($projectDefinition['data']['project']['forms'] as $form) {
            foreach ($form['inputs'] as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                    $numOfBranches++;
                }
            }
        }

        $access = $project->access;
        $this->assertZipContent($filePath, $format, $numOfForms, $numOfBranches, $access);

        Storage::delete($filePath);

        //delete all media files
        Storage::disk('audio')->deleteDirectory($project->ref);
        Storage::disk('photo')->deleteDirectory($project->ref);
        Storage::disk('video')->deleteDirectory($project->ref);
    }

    public function test_download_csv_private_multiple_forms_and_branches()
    {
        //create user
        $format = 'csv';
        $user = factory(User::class)->create();
        //create a project with a random number of forms and branches (min 1)
        $projectDefinition = ProjectDefinitionGenerator::createProject(rand(1, 5));
        $forms = $projectDefinition['data']['project']['forms'];
        $filesCountForm = sizeof($forms);
        $filesCountBranch = 0;
        foreach ($forms as $form) {
            foreach ($form['inputs'] as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $filesCountBranch++;
                }
            }
        }
        //create a project with existing name, slug and ref
        $project = factory(Project::class)->create(
            [
                'ref' => $projectDefinition['data']['project']['ref'],
                'name' => $projectDefinition['data']['project']['name'],
                'slug' => $projectDefinition['data']['project']['slug'],
                'created_by' => $user->id,
                'access' => config('epicollect.strings.project_access.private')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create(['user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')]);

        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'])]
        );

        factory(ProjectStats::class)->create(
            ['project_id' => $project->id,
                'total_entries' => 0]
        );

        // Convert data array to JSON
        $jsonData = json_encode($projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData);
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($user)
            ->call(
                'POST',
                'api/internal/formbuilder/' . $project->slug,
                [],
                [],
                [],
                [],
                $base64EncodedData
            );
        try {
            $response->assertStatus(200);
        } catch (Exception $exception) {
            $this->logTestError($exception, $response);
        }

        $cookies = [config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp];
        $params = [config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp,
            'format' => $format];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);

        $response->assertStatus(200);
        // Assert that the returned file is a zip file
        $this->assertTrue($response->headers->get('Content-Type') === 'application/zip');
        //assert filename
        $zipName = $project->slug . '-' . $params['format'] . '.zip';
        // Get the Content-Disposition header
        $contentDisposition = $response->headers->get('Content-Disposition');
        // Extract the filename from the header
        // Extract the filename from the header
        if (preg_match('/filename[^;=\n]*=["\']?([^"\']*)["\']?/', $contentDisposition, $matches)) {
            $extractedFilename = $matches[1];
        } else {
            $extractedFilename = null;
        }
        $this->assertEquals($zipName, $extractedFilename);
        // Get the response content as a file
        /** @noinspection PhpUndefinedMethodInspection */
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format, $filesCountForm, $filesCountBranch);

        Storage::delete($filePath);
    }

    public function test_download_json_private_multiple_forms_and_branches()
    {
        //create user
        $format = 'json';
        $user = factory(User::class)->create();
        //create a project with a random number of forms and branches (min 1)
        $projectDefinition = ProjectDefinitionGenerator::createProject(rand(1, 5));
        $forms = $projectDefinition['data']['project']['forms'];
        $filesCountForm = sizeof($forms);
        $filesCountBranch = 0;
        foreach ($forms as $form) {
            foreach ($form['inputs'] as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $filesCountBranch++;
                }
            }
        }
        //create a project with existing name, slug and ref
        $project = factory(Project::class)->create(
            [
                'ref' => $projectDefinition['data']['project']['ref'],
                'name' => $projectDefinition['data']['project']['name'],
                'slug' => $projectDefinition['data']['project']['slug'],
                'created_by' => $user->id,
                'access' => config('epicollect.strings.project_access.private')
            ]
        );

        //assign role
        factory(ProjectRole::class)->create(['user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')]);

        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'])]
        );

        factory(ProjectStats::class)->create(
            ['project_id' => $project->id,
                'total_entries' => 0]
        );


        // Convert data array to JSON
        $jsonData = json_encode($projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData);
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($user)
            ->call(
                'POST',
                'api/internal/formbuilder/' . $project->slug,
                [],
                [],
                [],
                [],
                $base64EncodedData
            );
        try {
            $response->assertStatus(200);
        } catch (Exception $exception) {
            $this->logTestError($exception, $response);
        }

        $cookies = [config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp];
        $params = [config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp,
            'format' => $format];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);

        $response->assertStatus(200);
        // Assert that the returned file is a zip file
        $this->assertTrue($response->headers->get('Content-Type') === 'application/zip');
        //assert filename
        $zipName = $project->slug . '-' . $params['format'] . '.zip';
        // Get the Content-Disposition header
        $contentDisposition = $response->headers->get('Content-Disposition');
        // Extract the filename from the header
        if (preg_match('/filename[^;=\n]*=["\']?([^"\']*)["\']?/', $contentDisposition, $matches)) {
            $extractedFilename = $matches[1];
        } else {
            $extractedFilename = null;
        }
        $this->assertEquals($zipName, $extractedFilename);
        // Get the response content as a file
        /** @noinspection PhpUndefinedMethodInspection */
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format, $filesCountForm, $filesCountBranch);

        Storage::delete($filePath);
    }

    public function test_error_response_with_wrong_params()
    {
        //create user
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('epicollect.strings.project_access.public')
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

        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $cookies = [config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp];
        $params = [
            config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp,
            'format' => 'ciao',
            'map_index' => 0
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);
        $response->assertStatus(400);

        $params = [
            config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp,
            'format' => 'gibberish',
            'map_index' => 0
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);
        $response->assertStatus(400);

        $params = [
            config('epicollect.setup.cookies.download_entries') => Carbon::now()->timestamp,
            'format' => 'csv',
            'map_index' => 'gibberish'
        ];
        $response = $this->actingAs($user)->call('GET', 'api/internal/download-entries/' . $project->slug, $params, $cookies);
        $response->assertStatus(400);

    }

    public function test_should_abort_if_timestamp_missing()
    {
        //create user
        $user = factory(User::class)->create();
        //create project
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('epicollect.strings.project_access.public')
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

        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        $this->actingAs($user)->get('api/internal/download-entries/' . $project->slug)
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
                'access' => config('epicollect.strings.project_access.public')
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

        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $response = $this
            ->actingAs($user)
            ->json('GET', 'api/internal/download-entries/' . $project->slug)
            ->withCookie(config('epicollect.setup.cookies.download_entries'), 'gibberish');


        //for reasons unknown when using withCookie() we need to change approach to test the response
        $this->assertEquals(400, $response->getStatusCode());

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

    private function assertZipContent($filePath, $extension, $filesCountForm, $filesCountBranch, $access = null)
    {
        $zip = new ZipArchive();
        $zip->open($filePath);
        $fileFound = false;
        $this->assertEquals($zip->numFiles, ($filesCountForm + $filesCountBranch));
        $filenamesForm = [];
        $filenamesBranch = [];

        // Check each file in the zip archive
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $extractedFilename = $fileInfo['name'];

            if (Str::startsWith($extractedFilename, 'form')) {
                $filenamesForm[] = $extractedFilename;
            }
            if (Str::startsWith($extractedFilename, 'branch')) {
                $filenamesBranch[] = $extractedFilename;
            }
            $startsWithForm = Str::startsWith($extractedFilename, 'form');
            $startsWithBranch = Str::startsWith($extractedFilename, 'branch');
            //assert filenames
            $this->assertTrue($startsWithForm || $startsWithBranch);

            //Check if the file has the correct extension
            if (pathinfo($extractedFilename, PATHINFO_EXTENSION) === $extension) {
                $fileFound = true;
                // Extract the file content and perform assertions
                $content = $zip->getFromIndex($i);
                // Asserts on the content or other validations
                $this->assertNotEmpty($content);

                //if the content is CSV, assert the urls are correct
                if ($extension === 'csv' && $access === config('epicollect.strings.project_access.public')) {
                    $this->assertCsvUrlsAreCorrect($content);
                }
                //if the extension is json, assert the urls are correct
                if ($extension === 'json' && $access === config('epicollect.strings.project_access.public')) {
                    $this->assertJsonUrlsAreCorrect($content);
                }
            }
        }

        $allFilenames = array_merge($filenamesForm, $filenamesBranch);
        $this->assertCount(count(array_unique($allFilenames)), $allFilenames);
        $this->assertEquals(sizeof($filenamesForm), $filesCountForm);
        $this->assertEquals(sizeof($filenamesBranch), $filesCountBranch);

        // Close the zip file
        $zip->close();
        // Assert that at least one file was found in the zip
        $this->assertTrue($fileFound);
    }

    /**
     * Parses CSV content and asserts that any URLs found adhere to the
     * correct query string format. Fails if the extension is not JPG or MP4.
     *
     * @param string $csvContent The raw CSV data.
     * @return void
     */
    private function assertCsvUrlsAreCorrect(string $csvContent): void
    {
        // ... (CSV file handling setup remains the same) ...
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csvContent);
        rewind($handle);

        $protocols = ['https://', 'http://'];

        // Read the CSV content row by row
        while (($data = fgetcsv($handle)) !== false) {
            foreach ($data as $field) {
                $value = trim($field);
                $isUrl = false;

                // 1. Check if the value starts with either the HTTP or HTTPS protocol
                foreach ($protocols as $protocol) {
                    if (Str::startsWith($value, $protocol)) {
                        $isUrl = true;
                        break;
                    }
                }

                if ($isUrl) {
                    $parsedUrl = parse_url($value);
                    $this->assertIsArray($parsedUrl, "Failed to parse URL: **$value**");

                    $queryString = $parsedUrl['query'] ?? '';
                    $queryData = [];
                    parse_str($queryString, $queryData);

                    // Get the filename from the 'name' query parameter
                    $filename = $queryData['name'] ?? null;
                    $this->assertNotEmpty($filename, "URL is missing the 'name' query parameter: **$value**");

                    // Extract the file extension from the filename
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                    $this->assertNotEmpty($extension, "Filename in 'name' parameter is missing a file extension: **$value**");

                    // Define the common UUID_timestamp regex for all media types
                    $uuidTimestampRegex = '/^([a-f0-9]{8}-([a-f0-9]{4}-){3}[a-f0-9]{12})_\d+\.' . preg_quote($extension) . '$/i';

                    // --- 2. FAIL if extension is NOT JPG or MP4 ---
                    $allowedExtensions = ['jpg', 'mp4'];
                    if (!in_array(strtolower($extension), $allowedExtensions)) {
                        $this->fail("URL uses an unsupported file extension (**.$extension**). Only JPG and MP4 are allowed: **$value**");
                    }

                    // --- 3. Validate Query String based on Extension/Media Type ---

                    if ($extension === 'jpg') {
                        // PHOTO Validation
                        $this->assertEquals(
                            'photo',
                            $queryData['type'] ?? null,
                            "Photo URL must have 'type=photo'. Found: **$value**"
                        );
                        $this->assertEquals(
                            'entry_original',
                            $queryData['format'] ?? null,
                            "Photo URL must have 'format=entry_original'. Found: **$value**"
                        );

                        // Apply UUID_timestamp regex
                        $this->assertMatchesRegularExpression(
                            $uuidTimestampRegex,
                            $filename,
                            "Photo filename does not match UUID_timestamp format: **$value**"
                        );

                    } elseif ($extension === 'mp4') {
                        // VIDEO/AUDIO Validation (Restricted to MP4)

                        $mediaType = $queryData['type'] ?? null;
                        $format = $queryData['format'] ?? null;

                        // Allow both 'video' and 'audio' types for .mp4 files (based on your production example)
                        $this->assertTrue(
                            in_array($mediaType, ['video', 'audio']),
                            "MP4 URL must have 'type=video' or 'type=audio'. Found: **$value**"
                        );

                        $this->assertEquals(
                            $mediaType,
                            $format,
                            "MP4 URL 'format' parameter must match 'type' parameter. Found: **$value**"
                        );

                        // Apply UUID_timestamp regex
                        $this->assertMatchesRegularExpression(
                            $uuidTimestampRegex,
                            $filename,
                            "Video/Audio filename does not match UUID_timestamp format: **$value**"
                        );

                    }
                    // No need for a final 'else' block, as the explicit check above handles all failures.
                }
            }
        }
        fclose($handle);
    }

    /**
     * Parses JSON content (array of objects) and asserts that any URL values
     * found adhere to the correct query string format. Fails if the extension is
     * not JPG or MP4.
     *
     * @param string $jsonContent The raw JSON string data.
     * @return void
     */
    private function assertJsonUrlsAreCorrect(string $jsonContent): void
    {
        $dataArray = json_decode($jsonContent, true);
        $this->assertIsArray($dataArray, 'JSON content is not a valid array of objects.');

        // Define constants and regex once
        $protocols = ['https://', 'http://'];
        $allowedExtensions = ['jpg', 'mp4'];

        // We pass $this (the test object) so the assertion methods can be called inside the helper
        foreach ($dataArray as $entry) {
            $this->assertIsArray($entry, 'JSON entry is not a valid object (associative array).');

            // Start the recursive traversal for the entry
            $this->traverseAndAssertJsonValue($entry, $protocols, $allowedExtensions);
        }
    }

    /**
     * Recursively traverses a value (string, array, or object) and asserts URL format.
     *
     * @param mixed $value The current value to check (could be string, array, or object).
     * @param array $protocols Allowed URL protocols.
     * @param array $allowedExtensions Allowed file extensions.
     * @return void
     */
    private function traverseAndAssertJsonValue(mixed $value, array $protocols, array $allowedExtensions): void
    {
        // If the value is an array or object, recurse through its elements/properties
        if (is_array($value) || is_object($value)) {
            foreach ($value as $nestedValue) {
                $this->traverseAndAssertJsonValue($nestedValue, $protocols, $allowedExtensions);
            }
            return;
        }

        // Only proceed if the value is a string
        if (!is_string($value)) {
            return;
        }

        $value = trim($value);
        $isUrl = false;

        // 1. Check if the value starts with a protocol
        foreach ($protocols as $protocol) {
            if (Str::startsWith($value, $protocol)) {
                $isUrl = true;
                break;
            }
        }

        if ($isUrl) {
            // --- URL ASSERTION LOGIC ---

            $parsedUrl = parse_url($value);
            $this->assertIsArray($parsedUrl, "Failed to parse URL: **$value**");

            $queryString = $parsedUrl['query'] ?? '';
            $queryData = [];
            parse_str($queryString, $queryData);

            // Get the filename from the 'name' query parameter
            $filename = $queryData['name'] ?? null;
            $this->assertNotEmpty($filename, "URL is missing the 'name' query parameter: **$value**");

            // Extract the file extension
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $this->assertNotEmpty($extension, "Filename in 'name' parameter is missing a file extension: **$value**");
            $extension = strtolower($extension);

            // Define the common UUID_timestamp regex
            $uuidTimestampRegex = '/^([a-f0-9]{8}-([a-f0-9]{4}-){3}[a-f0-9]{12})_\d+\.' . preg_quote($extension) . '$/i';

            // 2. FAIL if extension is NOT JPG or MP4
            if (!in_array($extension, $allowedExtensions)) {
                $this->fail("URL uses an unsupported file extension (**.$extension**). Only JPG and MP4 are allowed: **$value**");
            }

            // --- 3. Validate Query String based on Extension/Media Type ---

            if ($extension === 'jpg') {
                // PHOTO Validation
                $this->assertEquals(
                    'photo',
                    $queryData['type'] ?? null,
                    "Photo URL must have 'type=photo'. Found: **$value**"
                );
                $this->assertEquals(
                    'entry_original',
                    $queryData['format'] ?? null,
                    "Photo URL must have 'format=entry_original'. Found: **$value**"
                );

                $this->assertMatchesRegularExpression(
                    $uuidTimestampRegex,
                    $filename,
                    "Photo filename does not match UUID_timestamp format: **$value**"
                );

            } elseif ($extension === 'mp4') {
                // VIDEO/AUDIO Validation

                $mediaType = $queryData['type'] ?? null;
                $format = $queryData['format'] ?? null;

                $this->assertTrue(
                    in_array($mediaType, ['video', 'audio']),
                    "MP4 URL must have 'type=video' or 'type=audio'. Found: **$value**"
                );

                $this->assertEquals(
                    $mediaType,
                    $format,
                    "MP4 URL 'format' parameter must match 'type' parameter. Found: **$value**"
                );

                $this->assertMatchesRegularExpression(
                    $uuidTimestampRegex,
                    $filename,
                    "Video/Audio filename does not match UUID_timestamp format: **$value**"
                );
            }
        }
    }

}
