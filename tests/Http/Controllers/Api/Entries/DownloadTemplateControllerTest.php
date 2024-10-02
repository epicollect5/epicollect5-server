<?php

namespace Tests\Http\Controllers\Api\Entries;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Libraries\Utilities\Common;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DownloadTemplateControllerTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private $user;
    private $projectDefinition;
    private $project;

    public function setUp(): void
    {
        parent::setUp();

        //create a fake project with all the dependencies
        $user = factory(User::class)->create();
        $projectDefinition = ProjectDefinitionGenerator::createProject(5);
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private')
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
                'project_definition' => json_encode($projectDefinition['data'], JSON_UNESCAPED_SLASHES)
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        //upload the project definition via the formbuilder controller
        // Convert data array to JSON
        $jsonData = json_encode($projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = [];
        try {
            $response[] = $this->actingAs($user)
                ->call(
                    'POST',
                    'api/internal/formbuilder/' . $project->slug,
                    [],
                    [],
                    [],
                    [],
                    $base64EncodedData
                );

            $response[0]->assertStatus(200);
            $this->assertSame(json_decode($response[0]->getContent(), true), $projectDefinition);
            //assert there are no entries or branch entries
            $this->assertCount(0, Entry::where('project_id', $project->id)->get());
            $this->assertCount(0, BranchEntry::where('project_id', $project->id)->get());

            $this->user = $user;
            $this->project = $project;
            $this->projectDefinition = $projectDefinition;
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_should_redirect_if_user_not_logged_in_and_private_project()
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

        $queryString = '?map_index=0&form_index=0&branch_ref=&format=json';
        $response = [];
        $response[] = $this->get('api/internal/upload-headers/' . $project->slug . $queryString);
        $response[0]->assertStatus(404);

        $response[0]->assertJsonStructure([
            'errors' => [
                '*' => [
                    'code',
                    'title',
                    'source'
                ]
            ]
        ]);

        //imp: cannot use exactJson due to escaping the <br/>
        $response[0]->assertJsonFragment([
            "errors" => [
                [
                    "code" => "ec5_78",
                    "source" => "middleware",
                    "title" => "This project is private. <br/> You need permission to access it."
                ]
            ]
        ]);
    }

    public function test_should_send_template_response_json_if_public_project()
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

        $queryString = '?map_index=0&form_index=0&branch_ref=&format=json';
        $response = $this->get('api/internal/upload-headers/' . $project->slug . $queryString)
            ->assertStatus(200)
            ->assertJsonStructure([
                "data" => [
                    "headers" => []
                ]
            ])
            ->assertExactJson(
                [
                    "data" => [
                        "headers" => ["ec5_uuid", "1_Name"]
                    ]
                ]
            );
    }

    public function test_should_catch_project_does_not_exist()
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

        $wrongSlug = Generators::projectRef();
        $queryString = '?map_index=0&form_index=0&branch_ref=&format=json';
        $response = $this->get('api/internal/upload-headers/' . $wrongSlug . $queryString)
            ->assertStatus(404);
        $response->assertExactJson([
            "errors" => [
                [
                    "code" => "ec5_11",
                    "title" => "Project does not exist.",
                    "source" => "middleware"
                ]
            ]
        ]);
    }

    public function test_should_send_template_response_json_if_private_project()
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

        $queryString = '?map_index=0&form_index=0&branch_ref=&format=json';
        $response = $this->actingAs($user)
            ->get('api/internal/upload-headers/' . $project->slug . $queryString)
            ->assertStatus(200)
            ->assertJsonStructure([
                "data" => [
                    "headers" => []
                ]
            ])
            ->assertExactJson(
                [
                    "data" => [
                        "headers" => ["ec5_uuid", "1_Name"]
                    ]
                ]
            );
    }

    public function test_should_abort_if_timestamp_missing()
    {
        $filename = $this->project->slug . '__Form';
        $queryString = '?map_index=0';
        $queryString .= '&form_index=0';
        $queryString .= '&branch_ref=';
        $queryString .= '&format=csv';
        $queryString .= '&filename=' . $filename;
        $queryString .= '&epicollect5-download-entries=';
        $response = $this->actingAs($this->user)->get('api/internal/upload-template/' . $this->project->slug . $queryString)
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
                            "source" => "epicollect5-download-entries"
                        ],
                    ]
                ]
            );
    }

    public function test_send_response_as_csv_file_form($formIndex = 0)
    {
        $form = $this->projectDefinition['data']['project']['forms'][$formIndex];
        $mapIndex = 0;
        $filename = $this->project->slug . '__' . $form['name'];
        $filename .= '__EC5_AUTO__upload-template.csv';
        $queryString = '?map_index=' . $mapIndex;
        $queryString .= '&form_index=' . $formIndex;
        $queryString .= '&branch_ref=';
        $queryString .= '&format=csv';
        $queryString .= '&filename=' . $filename;
        $queryString .= '&epicollect5-download-entries=1234567890';
        $response = $this->actingAs($this->user)->get('api/internal/upload-template/' . $this->project->slug . $queryString)
            ->assertStatus(200);

        // Assert that the returned file is a csv file
        $this->assertTrue($response->headers->get('Content-Type') === 'text/csv; charset=UTF-8');
        // Get the Content-Disposition header
        $contentDisposition = $response->headers->get('Content-Disposition');
        // Extract the filename from the header
        preg_match('/filename="(.+)"/', $contentDisposition, $matches);
        $extractedFilename = $matches[1] ?? null;
        $this->assertEquals($filename, $extractedFilename);
        $this->assertCSVContentForm($response->getContent(), $mapIndex, $form);
    }

    public function test_send_response_as_csv_file_all_forms()
    {
        $formsMaxCount = config('epicollect.limits.formsMaxCount');
        for ($i = 0; $i < $formsMaxCount; $i++) {
            $this->test_send_response_as_csv_file_form($i);
        }
    }

    public function test_send_response_as_csv_file_branch($formIndex = 0)
    {
        $form = $this->projectDefinition['data']['project']['forms'][$formIndex];
        $inputs = $form['inputs'];
        $branchRef = '';
        $branchIndex = 0;
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $branchRef = $input['ref'];
                $branchIndex = $index;
                break;
            }
        }
        $branchName = $this->projectDefinition['data']['project']['forms'][$formIndex]['inputs'][$branchIndex]['question'];
        //truncate (and slugify) branch name to avoid super long file names
        $branchNameTruncated = Str::slug(substr(strtolower($branchName), 0, 100));
        $mapIndex = 0;
        $filename = $this->project->slug . '__' . $form['name'] . '__' . $branchNameTruncated;
        $filename .= '__EC5_AUTO__upload-template.csv';
        $queryString = '?map_index=' . $mapIndex;
        $queryString .= '&form_index=' . $formIndex;
        $queryString .= '&branch_ref=' . $branchRef;
        $queryString .= '&format=csv';
        $queryString .= '&filename=' . $filename;
        $queryString .= '&epicollect5-download-entries=1234567890';
        $response = $this->actingAs($this->user)->get('api/internal/upload-template/' . $this->project->slug . $queryString)
            ->assertStatus(200);

        // Assert that the returned file is a csv file
        $this->assertTrue($response->headers->get('Content-Type') === 'text/csv; charset=UTF-8');
        // Get the Content-Disposition header
        $contentDisposition = $response->headers->get('Content-Disposition');
        // Extract the filename from the header
        preg_match('/filename="(.+)"/', $contentDisposition, $matches);
        $extractedFilename = $matches[1] ?? null;
        $this->assertEquals($filename, $extractedFilename);
        $this->assertCSVContentBranch($response->getContent(), $mapIndex, $form, $branchRef, $branchIndex);
    }

    public function test_send_response_as_csv_file_all_branches()
    {
        $formsMaxCount = config('epicollect.limits.formsMaxCount');
        for ($i = 0; $i < $formsMaxCount; $i++) {
            $this->test_send_response_as_csv_file_branch($i);
        }
    }

    public function test_send_response_as_json_form($formIndex = 0)
    {
        $form = $this->projectDefinition['data']['project']['forms'][$formIndex];
        $mapIndex = 0;
        $filename = $this->project->slug . '__' . $form['name'];
        $filename .= '__EC5_AUTO__upload-template.csv';
        $queryString = '?map_index=' . $mapIndex;
        $queryString .= '&form_index=' . $formIndex;
        $queryString .= '&branch_ref=';
        $queryString .= '&format=json';
        $queryString .= '&filename=' . $filename;
        $queryString .= '&epicollect5-download-entries=1234567890';
        $response = $this->actingAs($this->user)->get('api/internal/upload-headers/' . $this->project->slug . $queryString)
            ->assertStatus(200)
            ->assertJsonStructure([
                "data" => [
                    "headers" => []
                ]
            ]);

        $JSONResponse = json_decode($response->getContent(), true);
        $this->assertJSONHeadersForm($JSONResponse['data']['headers'], $mapIndex, $form);
    }

    public function test_send_response_as_json_all_forms()
    {
        $formsMaxCount = config('epicollect.limits.formsMaxCount');
        for ($i = 0; $i < $formsMaxCount; $i++) {
            $this->test_send_response_as_json_form($i);
        }
    }

    public function test_send_response_as_json_branch($formIndex = 0)
    {
        $form = $this->projectDefinition['data']['project']['forms'][$formIndex];
        $inputs = $form['inputs'];
        $branchRef = '';
        $branchIndex = 0;
        foreach ($inputs as $index => $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $branchRef = $input['ref'];
                $branchIndex = $index;
                break;
            }
        }
        $mapIndex = 0;
        $queryString = '?map_index=' . $mapIndex;
        $queryString .= '&form_index=' . $formIndex;
        $queryString .= '&branch_ref=' . $branchRef;
        $queryString .= '&format=json';
        $queryString .= '&epicollect5-download-entries=1234567890';

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->get('api/internal/upload-headers/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200)
                ->assertJsonStructure([
                    "data" => [
                        "headers" => []
                    ]
                ]);

            $JSONResponse = json_decode($response[0]->getContent(), true);
            $this->assertJSONHeadersBranch($JSONResponse['data']['headers'], $mapIndex, $form, $branchRef, $branchIndex);
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_send_response_as_json_all_branches()
    {
        $formsMaxCount = config('epicollect.limits.formsMaxCount');
        for ($i = 0; $i < $formsMaxCount; $i++) {
            $this->test_send_response_as_json_branch($i);
        }
    }

    private function assertJSONHeadersForm($headers, $mapIndex, $form)
    {
        //get mapping
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $mapping = json_decode($projectStructure->project_mapping, true);
        $selectedMapping = $mapping[$mapIndex]['forms'][$form['ref']];
        $expectedHeaders = Common::getTemplateHeaders($form['inputs'], $selectedMapping, $mapTos[] = ['ec5_uuid']);
        $this->assertEquals($headers, $expectedHeaders);
    }

    private function assertJSONHeadersBranch($headers, $mapIndex, $form, $branchRef, $branchIndex)
    {
        //get mapping
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $mapping = json_decode($projectStructure->project_mapping, true);
        $selectedMapping = $mapping[$mapIndex]['forms'][$form['ref']][$branchRef]['branch'];
        $branchInputs = $form['inputs'][$branchIndex]['branch'];
        $expectedHeaders = Common::getTemplateHeaders($branchInputs, $selectedMapping, $mapTos[] = ['ec5_branch_uuid']);
        $this->assertEquals($headers, $expectedHeaders);
    }

    private function assertCSVContentForm($content, $mapIndex, $form)
    {
        $headers = explode(',', $content ?? '');
        //get mapping
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $mapping = json_decode($projectStructure->project_mapping, true);
        $selectedMapping = $mapping[$mapIndex]['forms'][$form['ref']];
        $expectedHeaders = Common::getTemplateHeaders($form['inputs'], $selectedMapping, $mapTos[] = ['ec5_uuid']);
        $this->assertEquals($headers, $expectedHeaders);
    }

    private function assertCSVContentBranch($content, $mapIndex, $form, $branchRef, $branchIndex)
    {
        $headers = explode(',', $content ?? '');
        //get mapping
        $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
        $mapping = json_decode($projectStructure->project_mapping, true);
        $selectedMapping = $mapping[$mapIndex]['forms'][$form['ref']][$branchRef]['branch'];
        $branchInputs = $form['inputs'][$branchIndex]['branch'];
        $expectedHeaders = Common::getTemplateHeaders($branchInputs, $selectedMapping, $mapTos[] = ['ec5_branch_uuid']);
        $this->assertEquals($headers, $expectedHeaders);
    }
}
