<?php

namespace Tests\Http\Controllers\Api\Entries\Download;

use Carbon\Carbon;
use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Storage;
use Tests\TestCase;
use Throwable;
use ZipArchive;

class DownloadSubsetControllerTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    public function test_redirect_if_user_not_logged_in()
    {
        //create user
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
        $cookieName = config('epicollect.setup.cookies.download_entries');
        $queryString = '?map_index=0&filename=ciao';
        $queryString .= '&' . $cookieName . '=test';
        $response = [];
        try {
            $response[] = $this->json('GET', 'api/internal/download-entries-subset/' . $project->slug . $queryString);
            $response[0]->assertStatus(404);
            $response[0]->assertJsonStructure([
                "errors" => [
                    [
                        "code",
                        "title",
                        "source",
                    ]
                ]
            ]);

            $response[0]->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_77",
                        "title" => "This project is private. Please log in.",
                        "source" => "middleware"
                    ]
                ]
            ]);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_download_json_public()
    {
        //create user
        $format = 'json';
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
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format, 1, 0);

        Storage::delete($filePath);
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
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format, 1, 0);

        Storage::delete($filePath);
    }

    public function test_download_csv_public()
    {
        //create user
        $format = 'csv';
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
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format, 1, 0);

        Storage::delete($filePath);
    }

    public function test_download_csv_private()
    {
        //create user
        $format = 'csv';
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
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format, 1, 0);

        Storage::delete($filePath);
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
        } catch (\Exception $exception) {
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
        } catch (\Exception $exception) {
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
        $responseContent = $response->getFile();
        // Get the downloaded file's path
        $filePath = $responseContent->getPathname();

        $this->assertZipContent($filePath, $format, $filesCountForm, $filesCountBranch);

        Storage::delete($filePath);
    }

    public function test_subset_filters_child_entries_by_parent_uuid()
    {
        $setup = $this->createSubsetTestProject(2);
        $user = $setup['user'];
        $project = $setup['project'];
        $entryGenerator = $setup['entryGenerator'];
        $parentFormRef = $setup['forms'][0];
        $childFormRef = $setup['forms'][1];
        $role = config('epicollect.strings.project_roles.creator');

        // Two parents, each with one child
        $parentUuids = [];
        $childUuids = [];
        foreach ([0, 1] as $i) {
            $parentPayload = $entryGenerator->createParentEntryPayload($parentFormRef);
            $entryGenerator->createParentEntryRow($user, $project, $role, $setup['projectDefinition'], $parentPayload);
            $parentUuids[$i] = $parentPayload['data']['id'];

            $childPayload = $entryGenerator->createChildEntryPayload($childFormRef, $parentFormRef, $parentUuids[$i]);
            $entryGenerator->createChildEntryRow($user, $project, $role, $setup['projectDefinition'], $childPayload);
            $childUuids[$i] = $childPayload['data']['id'];
        }

        $response = [];
        try {
            $params = $this->subsetParams([
                'form_ref' => $childFormRef,
                'parent_form_ref' => $parentFormRef,
                'parent_uuid' => $parentUuids[0],
            ]);
            $cookies = [config('epicollect.setup.cookies.download_entries') => $params[config('epicollect.setup.cookies.download_entries')]];
            $response[] = $this->actingAs($user)->call('GET', 'api/internal/download-entries-subset/' . $project->slug, $params, $cookies);
            $response[0]->assertStatus(200);

            $uuids = $this->subsetCsvUuids($response[0]->getFile()->getPathname());
            $this->assertEquals([$childUuids[0]], $uuids);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_subset_filters_branch_entries_by_branch_owner_uuid()
    {
        $setup = $this->createSubsetTestProject(1);
        $user = $setup['user'];
        $project = $setup['project'];
        $entryGenerator = $setup['entryGenerator'];
        $formRef = $setup['forms'][0];
        $role = config('epicollect.strings.project_roles.creator');

        // Resolve a branch input on form 0
        $branchRef = null;
        $branchInputs = null;
        foreach ($setup['projectDefinition']['data']['project']['forms'][0]['inputs'] as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branchRef = $input['ref'];
                $branchInputs = $input['branch'];
                break;
            }
        }
        $this->assertNotNull($branchRef, 'Expected at least one branch input in form 0');

        // Two owners, each with one branch entry
        $branchUuids = [];
        $ownerUuids = [];
        foreach ([0, 1] as $i) {
            $ownerPayload = $entryGenerator->createParentEntryPayload($formRef);
            $entryGenerator->createParentEntryRow($user, $project, $role, $setup['projectDefinition'], $ownerPayload);
            $ownerUuids[$i] = $ownerPayload['data']['id'];

            $branchPayload = $entryGenerator->createBranchEntryPayload($formRef, $branchInputs, $ownerUuids[$i], $branchRef);
            $entryGenerator->createBranchEntryRow($user, $project, $role, $setup['projectDefinition'], $branchPayload);
            $branchUuids[$i] = $branchPayload['data']['id'];
        }

        $response = [];
        try {
            $params = $this->subsetParams([
                'form_ref' => $formRef,
                'branch_ref' => $branchRef,
                'branch_owner_uuid' => $ownerUuids[1],
            ]);
            $cookies = [config('epicollect.setup.cookies.download_entries') => $params[config('epicollect.setup.cookies.download_entries')]];
            $response[] = $this->actingAs($user)->call('GET', 'api/internal/download-entries-subset/' . $project->slug, $params, $cookies);
            $response[0]->assertStatus(200);

            $uuids = $this->subsetCsvUuids($response[0]->getFile()->getPathname());
            $this->assertEquals([$branchUuids[1]], $uuids);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    private function createSubsetTestProject(int $formsCount): array
    {
        $user = factory(User::class)->create();
        $projectDefinition = ProjectDefinitionGenerator::createProject($formsCount);
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'name' => array_get($projectDefinition, 'data.project.name'),
            'slug' => array_get($projectDefinition, 'data.project.slug'),
            'ref' => array_get($projectDefinition, 'data.project.ref'),
            'access' => config('epicollect.strings.project_access.public'),
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator'),
        ]);

        $projectExtraService = new ProjectExtraService();
        $projectExtra = $projectExtraService->generateExtraStructure($projectDefinition['data']);
        $projectMappingService = new ProjectMappingService();
        $projectMapping = [$projectMappingService->createEC5AUTOMapping($projectExtra)];
        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'project_definition' => json_encode($projectDefinition['data']),
            'project_extra' => json_encode($projectExtra),
            'project_mapping' => json_encode($projectMapping),
        ]);
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0,
        ]);

        $forms = [];
        for ($i = 0; $i < $formsCount; $i++) {
            $forms[] = array_get($projectDefinition, 'data.project.forms.' . $i . '.ref');
        }

        return [
            'user' => $user,
            'project' => $project,
            'projectDefinition' => $projectDefinition,
            'entryGenerator' => new EntryGenerator($projectDefinition),
            'forms' => $forms,
        ];
    }

    private function subsetParams(array $overrides): array
    {
        $timestamp = Carbon::now()->timestamp;

        return array_merge([
            config('epicollect.setup.cookies.download_entries') => $timestamp,
            'format' => 'csv',
            'map_index' => 0,
            'filename' => 'subset-test.zip',
            'sort_by' => 'created_at',
            'sort_order' => 'DESC',
        ], $overrides);
    }

    private function subsetCsvUuids(string $zipFilepath): array
    {
        $zip = new ZipArchive();
        $zip->open($zipFilepath);
        $this->assertEquals(1, $zip->numFiles);
        $content = $zip->getFromIndex(0);
        $zip->close();
        Storage::delete($zipFilepath);

        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $content))));
        $header = str_getcsv(array_shift($lines));
        // Entry uuid column: ec5_uuid (form) or ec5_branch_uuid (branch)
        $uuidIndex = array_search('ec5_branch_uuid', $header);
        if ($uuidIndex === false) {
            $uuidIndex = array_search('ec5_uuid', $header);
        }
        $this->assertNotFalse($uuidIndex, 'Expected a uuid column in subset CSV header');
        $uuids = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            $uuids[] = $row[$uuidIndex];
        }
        sort($uuids);

        return $uuids;
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

        $response = $this->actingAs($user)->get('api/internal/download-entries/' . $project->slug)
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


        //for reasons unknown when using withCookie() we need to change approch to test the response
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

    private function assertZipContent($filePath, $extension, $filesCountForm, $filesCountBranch)
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
                // Additional assertions on content can be done here
            }
        }

        $allFilenames = array_merge($filenamesForm, $filenamesBranch);
        $this->assertCount(count(array_unique($allFilenames)), $allFilenames);
        $this->assertEquals(sizeof($filenamesForm), $filesCountForm);
        $this->assertEquals(sizeof($filenamesBranch), $filesCountBranch);

        // Close the zip file
        $zip->close();
        // Assert that at least one JSON file was found in the zip
        $this->assertTrue($fileFound);
    }
}
