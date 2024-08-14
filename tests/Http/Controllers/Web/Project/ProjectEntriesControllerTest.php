<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class ProjectEntriesControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    private $user;
    private $project;
    private $projectDefinition;
    private $entryGenerator;
    private $entriesLimits;
    private $limitTo;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->limitTo = rand(1, 100);
        $this->clearDatabase([]);
        $name = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.name');
        $slug = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.slug');
        $email = config('testing.UNIT_TEST_RANDOM_EMAIL');


        $response = [];
        try {
            //create fake user for testing
            $user = factory(User::class)->create(['email' => $email]);
            //create a project with custom project definition
            $projectDefinition = ProjectDefinitionGenerator::createProject(5);
            $projectDefinition['data']['project']['name'] = $name;
            $projectDefinition['data']['project']['slug'] = $slug;

            //build entries limits array
            $forms = $projectDefinition['data']['project']['forms'];
            $entriesLimits = [];
            foreach ($forms as $form) {
                $entriesLimits[$form['ref']] = $this->limitTo;
                //set limits on branches
                $inputs = $form['inputs'];
                foreach ($inputs as $input) {
                    if ($input['type'] === config('epicollect.strings.branch')) {
                        $entriesLimits[$input['ref']] = $this->limitTo;
                    }
                }
            }

            // $projectDefinition['data']['project']['entries_limits'] = $entriesLimits;

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
                    'total_entries' => 5//we are going to add them later
                ]
            );
            $entryGenerator = new EntryGenerator($projectDefinition);

            //upload the project definition via the formbuilder controller
            // Convert data array to JSON
            $jsonData = json_encode($projectDefinition);
            // Gzip Compression
            $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
            // Base64 Encoding
            $base64EncodedData = base64_encode($gzippedData);

            //see https://github.com/laravel/framework/issues/46455
            $response[] = $this->actingAs($user)
                ->call('POST', 'api/internal/formbuilder/' . $project->slug,
                    [],
                    [],
                    [],
                    [], $base64EncodedData);

            $response[0]->assertStatus(200);
            $this->assertSame(json_decode($response[0]->getContent(), true), $projectDefinition);
            //assert there are no entries or branch entries
            $this->assertCount(0, Entry::where('project_id', $project->id)->get());
            $this->assertCount(0, BranchEntry::where('project_id', $project->id)->get());

            //assert entries limits are empty
            $initialProjectStructure = ProjectStructure::where('project_id', $project->id)->first();
            $initialProjectDefinition = json_decode($initialProjectStructure->project_definition, true);
            $this->assertEquals($initialProjectDefinition['project']['entries_limits'], []);

            $this->user = $user;
            $this->project = $project;
            $this->projectDefinition = $projectDefinition;
            $this->entryGenerator = $entryGenerator;
            $this->entriesLimits = $entriesLimits;

        } catch (Exception $exception) {
            $this->logTestError($exception, $response);
        }

    }

    public function test_manage_entries_page_renders_correctly()
    {
        $response = $this
            ->actingAs($this->user, self::DRIVER)
            ->get('myprojects/' . $this->project->slug . '/manage-entries')
            ->assertStatus(200);
    }

    public function test_update_all_entries_limits()
    {
        //fake a request to manage-entries page, so we can get the back() url
        $response = $this
            ->actingAs($this->user, self::DRIVER)
            ->get('myprojects/' . $this->project->slug . '/manage-entries')
            ->assertStatus(200);
        // Get the previous URL before the redirection
        $previousUrl = url()->previous();

        //create a limit object with all forms and branches and send it
        $payload['_token'] = csrf_token();
        //set entries limits on payload
        $forms = $this->projectDefinition['data']['project']['forms'];
        $payload = [];
        foreach ($forms as $form) {
            $payload[$form['ref']] = [
                "setLimit" => "true",
                "limitTo" => $this->limitTo,
                "formRef" => $form['ref'],
                "branchRef" => ''
            ];
            //set limits on branches
            $inputs = $form['inputs'];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $payload[$input['ref']] = [
                        "setLimit" => "true",
                        "limitTo" => $this->limitTo,
                        "formRef" => $form['ref'],
                        "branchRef" => $input['ref']
                    ];
                }
            }
        }

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/manage-entries',
                    $payload
                );
            $response[0]->assertStatus(302);
            $response[0]->assertRedirect($previousUrl);
            $response[0]->assertSessionHas('message', 'ec5_200');

            //assert entries limits have been updated
            $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
            $projectDefinition = json_decode($projectStructure->project_definition, true);
            $this->assertEquals($projectDefinition['project']['entries_limits'], $this->entriesLimits);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_update_form_entries_limits()
    {
        //fake a request to manage-entries page, so we can get the back() url
        $response = $this
            ->actingAs($this->user, self::DRIVER)
            ->get('myprojects/' . $this->project->slug . '/manage-entries')
            ->assertStatus(200);
        // Get the previous URL before the redirection
        $previousUrl = url()->previous();

        //create a limit object with all forms and branches and send it
        $payload['_token'] = csrf_token();
        //set entries limits on payload
        $forms = $this->projectDefinition['data']['project']['forms'];
        $payload = [];
        foreach ($forms as $form) {
            $payload[$form['ref']] = [
                "setLimit" => "true",
                "limitTo" => $this->limitTo,
                "formRef" => $form['ref'],
                "branchRef" => ''
            ];
            //remove limits on branches c
            $inputs = $form['inputs'];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    unset($this->entriesLimits[$input['ref']]);
                }
            }
        }

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/manage-entries',
                    $payload
                );
            $response[0]->assertStatus(302);
            $response[0]->assertRedirect($previousUrl);
            $response[0]->assertSessionHas('message', 'ec5_200');

            //assert entries limits have been updated
            $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
            $projectDefinition = json_decode($projectStructure->project_definition, true);
            $this->assertEquals($projectDefinition['project']['entries_limits'], $this->entriesLimits);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_update_branch_entries_limits()
    {
        //fake a request to manage-entries page, so we can get the back() url
        $response = $this
            ->actingAs($this->user, self::DRIVER)
            ->get('myprojects/' . $this->project->slug . '/manage-entries')
            ->assertStatus(200);
        // Get the previous URL before the redirection
        $previousUrl = url()->previous();

        //create a limit object with all forms and branches and send it
        $payload['_token'] = csrf_token();
        //set entries limits on payload
        $forms = $this->projectDefinition['data']['project']['forms'];
        $payload = [];
        foreach ($forms as $form) {
            //remove limits from $this->entriesLimits
            unset($this->entriesLimits[$form['ref']]);
            //set limits on branches
            $inputs = $form['inputs'];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $payload[$input['ref']] = [
                        "setLimit" => "true",
                        "limitTo" => $this->limitTo,
                        "formRef" => $form['ref'],
                        "branchRef" => $input['ref']
                    ];
                }
            }
        }

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/manage-entries',
                    $payload
                );
            $response[0]->assertStatus(302);
            $response[0]->assertRedirect($previousUrl);
            $response[0]->assertSessionHas('message', 'ec5_200');

            //assert entries limits have been updated
            $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
            $projectDefinition = json_decode($projectStructure->project_definition, true);
            $this->assertEquals($projectDefinition['project']['entries_limits'], $this->entriesLimits);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_remove_limits()
    {
        //fake a request to manage-entries page, so we can get the back() url
        $response = $this
            ->actingAs($this->user, self::DRIVER)
            ->get('myprojects/' . $this->project->slug . '/manage-entries')
            ->assertStatus(200);
        // Get the previous URL before the redirection
        $previousUrl = url()->previous();

        //create a limit object with all forms and branches and send it
        $payload['_token'] = csrf_token();
        $payload = [];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post('myprojects/' . $this->project->slug . '/manage-entries',
                    $payload
                );
            $response[0]->assertStatus(302);
            $response[0]->assertRedirect($previousUrl);
            $response[0]->assertSessionHas('message', 'ec5_200');

            //assert entries limits have been updated
            $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
            $projectDefinition = json_decode($projectStructure->project_definition, true);
            $this->assertEquals($projectDefinition['project']['entries_limits'], []);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }
}