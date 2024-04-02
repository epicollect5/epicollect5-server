<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PublicRoutes;

use ec5\Libraries\Utilities\Common;
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
use Ramsey\Uuid\Uuid;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class UploadAppControllerLimitsTest extends TestCase
{
    use DatabaseTransactions;

    private $user;
    private $project;
    private $projectDefinition;
    private $entryGenerator;
    private $parentUuids;
    private $branchesCounter;

    private $endpoint = 'api/upload/';

    public function setUp()
    {
        parent::setUp();
        $this->faker = Faker::create();

        $name = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.name');
        $slug = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.slug');
        $email = config('testing.UNIT_TEST_RANDOM_EMAIL');

        try {
            //delete any leftovers
            User::where('email', $email)->delete();
            Project::where('slug', $slug)->delete();

            //create fake user for testing
            $user = factory(User::class)->create(['email' => $email]);
            //create a project with custom project definition
            $projectDefinition = ProjectDefinitionGenerator::createProject(5);
            $projectDefinition['data']['project']['name'] = $name;
            $projectDefinition['data']['project']['slug'] = $slug;

            //set entries limits
            $forms = $projectDefinition['data']['project']['forms'];
            $entriesLimits = [];
            foreach ($forms as $form) {
                $entriesLimits[$form['ref']] = 1;
                //set limits on branches
                $inputs = $form['inputs'];
                foreach ($inputs as $input) {
                    if ($input['type'] === config('epicollect.strings.branch')) {
                        $entriesLimits[$input['ref']] = 1;
                    }
                }
            }

            $projectDefinition['data']['project']['entries_limits'] = $entriesLimits;
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
            $response = $this->actingAs($user)
                ->call('POST', 'api/internal/formbuilder/' . $project->slug,
                    [],
                    [],
                    [],
                    [], $base64EncodedData);

            $response->assertStatus(200);
            $this->assertSame(json_decode($response->getContent(), true), $projectDefinition);
            //assert there are no entries or branch entries
            $this->assertCount(0, Entry::where('project_id', $project->id)->get());
            $this->assertCount(0, BranchEntry::where('project_id', $project->id)->get());

            $parentUuids = [];
            foreach ($forms as $key => $form) {
                $this->branchesCounter = 0;
                $entry = factory(Entry::class)->create([
                    'project_id' => $project->id,
                    'parent_uuid' => $parentUuids[$key - 1] ?? '',
                    'form_ref' => $form['ref'],
                    'parent_form_ref' => $forms[$key - 1]['ref'] ?? '',
                    'child_counts' => 1//should be zero for last form but does not matter for testing
                ]);
                $parentUuids[] = $entry->uuid;

                //add a branch entry
                $inputs = $form['inputs'];
                foreach ($inputs as $input) {
                    if ($input['type'] === config('epicollect.strings.branch')) {
                        $this->branchesCounter++;
                        $branchEntry = factory(BranchEntry::class)->create(
                            [
                                'project_id' => $project->id,
                                'user_id' => $user->id,
                                'form_ref' => $form['ref'],
                                'owner_entry_id' => $entry->id,
                                'owner_uuid' => $entry->uuid,
                                'owner_input_ref' => $input['ref'],
                                'uuid' => Uuid::uuid4()->toString()
                            ]
                        );
                    }
                }
            }

            $this->assertCount(sizeof($forms), Entry::where('project_id', $project->id)->get());
            $this->assertCount(sizeof($forms) * $this->branchesCounter, BranchEntry::where('project_id', $project->id)->get());

            $this->user = $user;
            $this->project = $project;
            $this->projectDefinition = $projectDefinition;
            $this->entryGenerator = $entryGenerator;
            $this->parentUuids = $parentUuids;
            $this->deviceId = Common::generateRandomHex();


        } catch (Exception $exception) {
            $this->logTestError($exception, $response);
        }

    }

    public function test_it_should_hit_form_limit_0()
    {
        $response = [];
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //generate a fake entry for the top parent form
            $entry = $this->entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_hit_form_limit_1()
    {
        $response = [];
        try {

            $parentFormRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            $childFormRef = array_get($this->projectDefinition, 'data.project.forms.1.ref');
            //generate a fake entry for child form level 1
            $entry = $this->entryGenerator->createChildEntryPayload($childFormRef, $parentFormRef, $this->parentUuids[0]);
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_hit_form_limit_2()
    {
        $response = [];
        try {

            $parentFormRef = array_get($this->projectDefinition, 'data.project.forms.1.ref');
            $childFormRef = array_get($this->projectDefinition, 'data.project.forms.2.ref');
            //generate a fake entry for child form level 1
            $entry = $this->entryGenerator->createChildEntryPayload($childFormRef, $parentFormRef, $this->parentUuids[1]);
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_hit_form_limit_3()
    {
        $response = [];
        try {

            $parentFormRef = array_get($this->projectDefinition, 'data.project.forms.2.ref');
            $childFormRef = array_get($this->projectDefinition, 'data.project.forms.3.ref');
            //generate a fake entry for child form level 1
            $entry = $this->entryGenerator->createChildEntryPayload($childFormRef, $parentFormRef, $this->parentUuids[2]);
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_hit_form_limit_4()
    {
        $response = [];
        try {

            $parentFormRef = array_get($this->projectDefinition, 'data.project.forms.3.ref');
            $childFormRef = array_get($this->projectDefinition, 'data.project.forms.4.ref');
            //generate a fake entry for child form level 1
            $entry = $this->entryGenerator->createChildEntryPayload($childFormRef, $parentFormRef, $this->parentUuids[3]);
            //perform a web upload
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $entry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_hit_branch_entry_limit_0()
    {
        $response = [];
        try {
            $uuid = $this->parentUuids[0];
            $ownerFormRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $this->entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_hit_branch_entry_limit_1()
    {
        $response = [];
        try {
            $uuid = $this->parentUuids[1];
            $ownerFormRef = array_get($this->projectDefinition, 'data.project.forms.1.ref');
            $inputs = $this->projectDefinition['data']['project']['forms'][1]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $this->entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_hit_branch_entry_limit_2()
    {
        $response = [];
        try {
            $uuid = $this->parentUuids[2];
            $ownerFormRef = array_get($this->projectDefinition, 'data.project.forms.2.ref');
            $inputs = $this->projectDefinition['data']['project']['forms'][2]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $this->entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_hit_branch_entry_limit_3()
    {
        $response = [];
        try {
            $uuid = $this->parentUuids[3];
            $ownerFormRef = array_get($this->projectDefinition, 'data.project.forms.3.ref');
            $inputs = $this->projectDefinition['data']['project']['forms'][3]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $this->entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_hit_branch_entry_limit_4()
    {
        $response = [];
        try {
            $uuid = $this->parentUuids[4];
            $ownerFormRef = array_get($this->projectDefinition, 'data.project.forms.4.ref');
            $inputs = $this->projectDefinition['data']['project']['forms'][4]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $this->entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $branchEntry);
            $response[0]->assertStatus(400)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_250",
                                "title" => "Entries Limit Reached. Please contact the Project Manager to add entries.",
                                "source" => "upload-controller"
                            ]
                        ]
                    ]
                );

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }
}