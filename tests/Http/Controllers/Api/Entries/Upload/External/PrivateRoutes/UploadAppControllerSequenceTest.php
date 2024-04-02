<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\External\PrivateRoutes;

use ec5\Libraries\Utilities\Common;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Auth;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

/* We cannot do multiple post requests in the same test method,
   as the app boots only once, and we are going to have side effects
   https://github.com/laravel/framework/issues/27060
   therefore, we use concatenation of @depends
 */

class UploadAppControllerSequenceTest extends TestCase
{
    use Assertions;

    private $endpoint = 'api/upload/';

    public function setUp()
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->deviceId = Common::generateRandomHex();
    }

    public function test_should_create_fake_project()
    {
        $name = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.name');
        $slug = config('testing.WEB_UPLOAD_CONTROLLER_PROJECT.slug');
        $email = config('testing.UNIT_TEST_RANDOM_EMAIL');
        //clean the database from leftovers, if any, since we are not using transactions
        $leftoverUser = User::where('email', $email)->first();
        $leftOverProject = Project::where('slug', $slug)->where('name', $name)->first();
        if ($leftOverProject || $leftoverUser) {
            $this->clearDatabase(['user' => $leftoverUser, 'project' => $leftOverProject]);
        }
        //create a project with custom project definition
        //create fake user for testing
        try {
            $user = factory(User::class)->create(['email' => $email]);
            $projectDefinition = ProjectDefinitionGenerator::createProject(5);
            $projectDefinition['data']['project']['name'] = $name;
            $projectDefinition['data']['project']['slug'] = $slug;
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

            return [
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $exception) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($exception, $response);
        }
    }

    /**
     * @depends test_should_create_fake_project
     */
    public function test_it_should_catch_user_not_logged_in($params)
    {
        $user = $params['user'];
        $projectDefinition = $params['projectDefinition'];
        $project = $params['project'];
        $entryGenerator = $params['entryGenerator'];
        $response = [];
        try {
            //get top parent formRef
            $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
            //generate a fake entry for the top parent form
            $entry = $entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
            $response[] = $this->post($this->endpoint . $project->slug, $entry);
            $response[0]->assertStatus(404)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_77",
                                "source" => "middleware",
                                "title" => "This project is private. Please log in."
                            ]
                        ]
                    ]
                );


            return [
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            //dd($e->getMessage(), $response, json_encode($entry), json_encode($projectDefinition));
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_catch_user_not_logged_in
     */
    public function test_it_should_catch_user_logged_in_but_not_a_member($params)
    {
        $notAMember = factory(User::class)->create();
        $user = $params['user'];
        $projectDefinition = $params['projectDefinition'];
        $project = $params['project'];
        $entryGenerator = $params['entryGenerator'];
        $response = [];
        try {
            //get top parent formRef
            $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
            //generate a fake entry for the top parent form
            $entry = $entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
            Auth::guard('api_external')->login($notAMember);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $entry);
            $response[0]->assertStatus(404)
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_78",
                                "source" => "middleware",
                                "title" => "This project is private. <br/> You need permission to access it."
                            ]
                        ]
                    ]
                );

            return [
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            //dd($e->getMessage(), $response, json_encode($entry), json_encode($projectDefinition));
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_catch_user_logged_in_but_not_a_member
     */
    public function test_it_should_upload_a_top_hierarchy_entry($params)
    {
        $response = [];
        try {
            $user = $params['user'];
            $projectDefinition = $params['projectDefinition'];
            $project = $params['project'];
            $entryGenerator = $params['entryGenerator'];
            //get top parent formRef
            $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
            //generate a fake payload for the top parent form
            $payload = $entryGenerator->createParentEntryPayload($formRef, $this->deviceId);
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $payload);
            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $uuid = $payload['data']['id'];
            $this->assertCount(1, Entry::where('project_id', $project->id)->get());
            $this->assertEquals(0, Entry::where('uuid', $uuid)->value('child_counts'));

            //payload should be assigned to currently logged-in user
            $entryFromDB = Entry::where('uuid', $uuid)->first();
            $this->assertEquals($user->id, $entryFromDB->user_id);

            $entryFromPayload = $payload['data']['entry'];
            $this->assertEquals($entryFromDB->uuid, $entryFromPayload['entry_uuid']);
            $this->assertEquals($entryFromDB->title, $entryFromPayload['title']);
            //timestamp
            $this->assertEquals(
                str_replace(' ', 'T', $entryFromDB->created_at) . '.000Z',
                $entryFromPayload['created_at']);


            //assert payload stored vs. payload uploaded
            $this->assertEntryStoredAgainstEntryPayload(
                $entryFromDB,
                $entryFromPayload,
                $projectDefinition
            );

            return [
                'entry' => $payload,
                'parentEntry' => null,
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            //dd($e->getMessage(), $response, json_encode($payload), json_encode($projectDefinition));
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_upload_a_top_hierarchy_entry
     */
    public function test_it_should_upload_a_child_entry_level_1($params)
    {
        $entry = $params['entry'];
        $user = $params['user'];
        $projectDefinition = $params['projectDefinition'];
        $project = $params['project'];
        $entryGenerator = $params['entryGenerator'];

        $response = [];
        try {

            //now generate a child form entry (level 1)
            $parentEntryUuid = $entry['data']['id'];
            $parentFormRef = array_get($projectDefinition, 'data.project.forms.0.ref');
            $childFormRef = array_get($projectDefinition, 'data.project.forms.1.ref');
            $childEntry1 = $entryGenerator->createChildEntryPayload(
                $childFormRef,
                $parentFormRef,
                $parentEntryUuid,
                $this->deviceId
            );

            //post the child entry
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $childEntry1);
            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(2, Entry::where('project_id', $project->id)->get());
            $this->assertCount(1, Entry::where('form_ref', $childFormRef)->get());
            $this->assertEquals(1, Entry::where('uuid', $parentEntryUuid)->value('child_counts'));
            $this->assertEquals(0, Entry::where('uuid', $childEntry1['data']['id'])->value('child_counts'));
            //entry should be assigned to currently logged-in user
            $this->assertEquals($user->id, Entry::where('uuid', $childEntry1['data']['id'])->value('user_id'));

            //assert payload stored vs. payload uploaded
            $entryFromDB = Entry::where('uuid', $childEntry1['data']['id'])->first();
            $this->assertEquals($user->id, $entryFromDB->user_id);
            $entryFromPayload = $childEntry1['data']['entry'];
            $this->assertEntryStoredAgainstEntryPayload(
                $entryFromDB,
                $entryFromPayload,
                $projectDefinition,
                1
            );

            return [
                'entry' => $entry,
                'childEntry1' => $childEntry1,
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_upload_a_child_entry_level_1
     */
    public function test_it_should_upload_a_child_entry_level_2($params)
    {
        $response = [];
        try {
            $entry = $params['entry'];
            $childEntry1 = $params['childEntry1'];
            $user = $params['user'];
            $projectDefinition = $params['projectDefinition'];
            $project = $params['project'];
            $entryGenerator = $params['entryGenerator'];
            //now generate a child form entry (level 1)
            $parentEntryUuid = $childEntry1['data']['id'];
            $parentFormRef = array_get($projectDefinition, 'data.project.forms.1.ref');
            $childFormRef = array_get($projectDefinition, 'data.project.forms.2.ref');
            $childEntry2 = $entryGenerator->createChildEntryPayload(
                $childFormRef,
                $parentFormRef,
                $parentEntryUuid,
                $this->deviceId
            );

            //post the child entry
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $childEntry2);

            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(3, Entry::where('project_id', $project->id)->get());
            $this->assertCount(1, Entry::where('form_ref', $childFormRef)->get());
            $this->assertEquals(1, Entry::where('uuid', $entry['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(1, Entry::where('uuid', $childEntry1['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(0, Entry::where('uuid', $childEntry2['data']['id'])
                ->value('child_counts'));

            //entry should be assigned to currently logged-in user
            $this->assertEquals($user->id, Entry::where('uuid', $childEntry2['data']['id'])->value('user_id'));

            //assert payload stored vs. payload uploaded
            $entryFromDB = Entry::where('uuid', $childEntry2['data']['id'])->first();
            $this->assertEquals($user->id, $entryFromDB->user_id);
            $entryFromPayload = $childEntry2['data']['entry'];
            $this->assertEntryStoredAgainstEntryPayload(
                $entryFromDB,
                $entryFromPayload,
                $projectDefinition,
                2
            );

            return [
                'entry' => $entry,
                'childEntry1' => $childEntry1,
                'childEntry2' => $childEntry2,
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($e, $response);
            //dd($e->getMessage(), $response, json_encode($entry), json_encode($projectDefinition));
        }
    }

    /**
     * @depends test_it_should_upload_a_child_entry_level_2
     */
    public function test_it_should_upload_a_child_entry_level_3($params)
    {
        $response = [];
        try {
            $entry = $params['entry'];
            $childEntry1 = $params['childEntry1'];
            $childEntry2 = $params['childEntry2'];
            $user = $params['user'];
            $projectDefinition = $params['projectDefinition'];
            $project = $params['project'];
            $entryGenerator = $params['entryGenerator'];
            //now generate a child form entry (level 1)
            $parentEntryUuid = $childEntry2['data']['id'];
            $parentFormRef = array_get($projectDefinition, 'data.project.forms.2.ref');
            $childFormRef = array_get($projectDefinition, 'data.project.forms.3.ref');
            $childEntry3 = $entryGenerator->createChildEntryPayload(
                $childFormRef,
                $parentFormRef,
                $parentEntryUuid,
                $this->deviceId
            );

            //post the child entry
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $childEntry3);

            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(4, Entry::where('project_id', $project->id)->get());
            $this->assertCount(1, Entry::where('form_ref', $childFormRef)->get());

            $this->assertEquals(1, Entry::where('uuid', $entry['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(1, Entry::where('uuid', $childEntry1['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(1, Entry::where('uuid', $childEntry2['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(0, Entry::where('uuid', $childEntry3['data']['id'])
                ->value('child_counts'));

            //entry should be assigned to currently logged-in user
            $this->assertEquals($user->id, Entry::where('uuid', $childEntry3['data']['id'])->value('user_id'));

            //assert payload stored vs. payload uploaded
            $entryFromDB = Entry::where('uuid', $childEntry3['data']['id'])->first();
            $this->assertEquals($user->id, $entryFromDB->user_id);
            $entryFromPayload = $childEntry3['data']['entry'];
            $this->assertEntryStoredAgainstEntryPayload(
                $entryFromDB,
                $entryFromPayload,
                $projectDefinition,
                3
            );

            return [
                'entry' => $entry,
                'childEntry1' => $childEntry1,
                'childEntry2' => $childEntry2,
                'childEntry3' => $childEntry3,
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_upload_a_child_entry_level_3
     */
    public function test_it_should_upload_a_child_entry_level_4($params)
    {
        $response = [];
        try {
            $entry = $params['entry'];
            $childEntry1 = $params['childEntry1'];
            $childEntry2 = $params['childEntry2'];
            $childEntry3 = $params['childEntry3'];
            $user = $params['user'];
            $projectDefinition = $params['projectDefinition'];
            $project = $params['project'];
            $entryGenerator = $params['entryGenerator'];
            //now generate a child form entry (level 1)
            $parenEntryUuid = $childEntry3['data']['id'];
            $parentFormRef = array_get($projectDefinition, 'data.project.forms.3.ref');
            $childFormRef = array_get($projectDefinition, 'data.project.forms.4.ref');
            $childEntry4 = $entryGenerator->createChildEntryPayload(
                $childFormRef,
                $parentFormRef,
                $parenEntryUuid,
                $this->deviceId
            );

            //post the child entry
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $childEntry4);

            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(5, Entry::where('project_id', $project->id)->get());
            $this->assertCount(1, Entry::where('form_ref', $childFormRef)->get());

            $this->assertEquals(1, Entry::where('uuid', $entry['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(1, Entry::where('uuid', $childEntry1['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(1, Entry::where('uuid', $childEntry2['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(1, Entry::where('uuid', $childEntry3['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(0, Entry::where('uuid', $childEntry4['data']['id'])
                ->value('child_counts'));

            //entry should be assigned to currently logged-in user
            $this->assertEquals($user->id, Entry::where('uuid', $childEntry4['data']['id'])->value('user_id'));

            //assert payload stored vs. payload uploaded
            $entryFromDB = Entry::where('uuid', $childEntry4['data']['id'])->first();
            $this->assertEquals($user->id, $entryFromDB->user_id);
            $entryFromPayload = $childEntry4['data']['entry'];
            $this->assertEntryStoredAgainstEntryPayload(
                $entryFromDB,
                $entryFromPayload,
                $projectDefinition,
                4
            );

            return [
                'entry' => $entry,
                'childEntry1' => $childEntry1,
                'childEntry2' => $childEntry2,
                'childEntry3' => $childEntry3,
                'childEntry4' => $childEntry4,
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_upload_a_child_entry_level_4
     */
    public function test_it_should_upload_branch_entry_0($params)
    {
        $response = [];
        try {
            $entry = $params['entry'];
            $childEntry1 = $params['childEntry1'];
            $childEntry2 = $params['childEntry2'];
            $childEntry3 = $params['childEntry3'];
            $childEntry4 = $params['childEntry4'];
            $user = $params['user'];
            $projectDefinition = $params['projectDefinition'];
            $project = $params['project'];
            $entryGenerator = $params['entryGenerator'];

            $uuid = $entry['data']['id'];
            $ownerFormRef = $entry['data']['attributes']['form']['ref'];
            $inputs = $projectDefinition['data']['project']['forms'][0]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $branchEntry);
            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(
                1,
                BranchEntry::where(
                    'project_id', $project->id)
                    //   ->where('owner_uuid', $uuid)
                    ->get());

            $this->assertEquals(1, Entry::where('uuid', $entry['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(1, Entry::where('uuid', $childEntry1['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(1, Entry::where('uuid', $childEntry2['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(1, Entry::where('uuid', $childEntry3['data']['id'])
                ->value('child_counts'));
            $this->assertEquals(0, Entry::where('uuid', $childEntry4['data']['id'])
                ->value('child_counts'));

            $branchCounts = json_decode(
                Entry::where('uuid', $entry['data']['id'])
                    ->value('branch_counts'), true);
            $this->assertEquals([
                $branches[0]['ref'] => 1,
                $branches[1]['ref'] => 0,//branch was deleted
            ], $branchCounts);

            //entry should be assigned to currently logged-in user
            $this->assertEquals($user->id, BranchEntry::where('uuid', $branchEntry['data']['id'])->value('user_id'));

            //assert payload stored vs. payload uploaded
            $branchEntryFromDB = BranchEntry::where('uuid', $branchEntry['data']['id'])->first();
            $this->assertEquals($user->id, $branchEntryFromDB->user_id);
            $entryFromPayload = $branchEntry['data']['branch_entry'];
            $this->assertBranchEntryStoredAgainstBranchEntryPayload(
                $branchEntryFromDB,
                $entryFromPayload,
                $projectDefinition,
                $branches[0]['ref'],
                0
            );

            return [
                'entry' => $entry,
                'childEntry1' => $childEntry1,
                'childEntry2' => $childEntry2,
                'childEntry3' => $childEntry3,
                'childEntry4' => $childEntry4,
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_upload_branch_entry_0
     */
    public function test_it_should_upload_branch_entry_1($params)
    {
        $entry = $params['entry'];
        $childEntry1 = $params['childEntry1'];
        $childEntry2 = $params['childEntry2'];
        $childEntry3 = $params['childEntry3'];
        $childEntry4 = $params['childEntry4'];
        $user = $params['user'];
        $projectDefinition = $params['projectDefinition'];
        $project = $params['project'];
        $entryGenerator = $params['entryGenerator'];

        $response = [];
        try {
            $uuid = $childEntry1['data']['id'];
            $ownerFormRef = $childEntry1['data']['attributes']['form']['ref'];
            $inputs = $projectDefinition['data']['project']['forms'][1]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $branchEntry);
            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(
                1,
                BranchEntry::where(
                    'form_ref', $ownerFormRef)
                    //   ->where('owner_uuid', $uuid)
                    ->get());
            $this->assertCount(
                2,
                BranchEntry::where(
                    'project_id', $project->id)
                    //   ->where('owner_uuid', $uuid)
                    ->get());

            $branchCounts = json_decode(Entry::where('uuid', $uuid)
                ->value('branch_counts'), true);

            $this->assertEquals([
                $branches[0]['ref'] => 1,
                $branches[1]['ref'] => 0
            ], $branchCounts);

            //entry should be assigned to currently logged-in user
            $this->assertEquals($user->id, BranchEntry::where('uuid', $branchEntry['data']['id'])->value('user_id'));

            //assert payload stored vs. payload uploaded
            $branchEntryFromDB = BranchEntry::where('uuid', $branchEntry['data']['id'])->first();
            $this->assertEquals($user->id, $branchEntryFromDB->user_id);
            $entryFromPayload = $branchEntry['data']['branch_entry'];
            $this->assertBranchEntryStoredAgainstBranchEntryPayload(
                $branchEntryFromDB,
                $entryFromPayload,
                $projectDefinition,
                $branches[0]['ref'],
                1
            );


            return [
                'entry' => $entry,
                'childEntry1' => $childEntry1,
                'childEntry2' => $childEntry2,
                'childEntry3' => $childEntry3,
                'childEntry4' => $childEntry4,
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_upload_branch_entry_1
     */
    public function test_it_should_upload_branch_entry_2($params)
    {
        $entry = $params['entry'];
        $childEntry1 = $params['childEntry1'];
        $childEntry2 = $params['childEntry2'];
        $childEntry3 = $params['childEntry3'];
        $childEntry4 = $params['childEntry4'];
        $user = $params['user'];
        $projectDefinition = $params['projectDefinition'];
        $project = $params['project'];
        $entryGenerator = $params['entryGenerator'];

        $response = [];
        try {
            $uuid = $childEntry2['data']['id'];
            $ownerFormRef = $childEntry2['data']['attributes']['form']['ref'];
            $inputs = $projectDefinition['data']['project']['forms'][2]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $branchEntry);
            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(
                1,
                BranchEntry::where(
                    'form_ref', $ownerFormRef)
                    //   ->where('owner_uuid', $uuid)
                    ->get());
            $this->assertCount(
                3,
                BranchEntry::where(
                    'project_id', $project->id)
                    //   ->where('owner_uuid', $uuid)
                    ->get());

            $branchCounts = json_decode(Entry::where('uuid', $uuid)
                ->value('branch_counts'), true);

            $this->assertEquals([
                $branches[0]['ref'] => 1,
                $branches[1]['ref'] => 0,//branch was deleted
            ], $branchCounts);

            //entry should be assigned to currently logged-in user
            $this->assertEquals($user->id, BranchEntry::where('uuid', $branchEntry['data']['id'])->value('user_id'));

            //assert payload stored vs. payload uploaded
            $branchEntryFromDB = BranchEntry::where('uuid', $branchEntry['data']['id'])->first();
            $this->assertEquals($user->id, $branchEntryFromDB->user_id);
            $entryFromPayload = $branchEntry['data']['branch_entry'];
            $this->assertBranchEntryStoredAgainstBranchEntryPayload(
                $branchEntryFromDB,
                $entryFromPayload,
                $projectDefinition,
                $branches[0]['ref'],
                2
            );

            return [
                'entry' => $entry,
                'childEntry1' => $childEntry1,
                'childEntry2' => $childEntry2,
                'childEntry3' => $childEntry3,
                'childEntry4' => $childEntry4,
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_upload_branch_entry_2
     */
    public function test_it_should_upload_branch_entry_3($params)
    {
        $entry = $params['entry'];
        $childEntry1 = $params['childEntry1'];
        $childEntry2 = $params['childEntry2'];
        $childEntry3 = $params['childEntry3'];
        $childEntry4 = $params['childEntry4'];
        $user = $params['user'];
        $projectDefinition = $params['projectDefinition'];
        $project = $params['project'];
        $entryGenerator = $params['entryGenerator'];

        $response = [];
        try {
            $uuid = $childEntry3['data']['id'];
            $ownerFormRef = $childEntry3['data']['attributes']['form']['ref'];
            $inputs = $projectDefinition['data']['project']['forms'][3]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $branchEntry);
            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(
                1,
                BranchEntry::where(
                    'form_ref', $ownerFormRef)
                    //   ->where('owner_uuid', $uuid)
                    ->get());
            $this->assertCount(
                4,
                BranchEntry::where(
                    'project_id', $project->id)
                    //   ->where('owner_uuid', $uuid)
                    ->get());

            $branchCounts = json_decode(Entry::where('uuid', $uuid)
                ->value('branch_counts'), true);

            $this->assertEquals([
                $branches[0]['ref'] => 1,
                $branches[1]['ref'] => 0,//branch was deleted
            ], $branchCounts);

            //entry should be assigned to currently logged-in user
            $this->assertEquals($user->id, BranchEntry::where('uuid', $branchEntry['data']['id'])->value('user_id'));

            //assert payload stored vs. payload uploaded
            $branchEntryFromDB = BranchEntry::where('uuid', $branchEntry['data']['id'])->first();
            $this->assertEquals($user->id, $branchEntryFromDB->user_id);
            $entryFromPayload = $branchEntry['data']['branch_entry'];
            $this->assertBranchEntryStoredAgainstBranchEntryPayload(
                $branchEntryFromDB,
                $entryFromPayload,
                $projectDefinition,
                $branches[0]['ref'],
                3
            );

            return [
                'entry' => $entry,
                'childEntry1' => $childEntry1,
                'childEntry2' => $childEntry2,
                'childEntry3' => $childEntry3,
                'childEntry4' => $childEntry4,
                'user' => $user,
                'projectDefinition' => $projectDefinition,
                'project' => $project,
                'entryGenerator' => $entryGenerator
            ];
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($e, $response);
        }
    }

    /**
     * @depends test_it_should_upload_branch_entry_3
     */
    public function test_it_should_upload_branch_entry_4($params)
    {
        $childEntry4 = $params['childEntry4'];
        $user = $params['user'];
        $projectDefinition = $params['projectDefinition'];
        $project = $params['project'];
        $entryGenerator = $params['entryGenerator'];

        $response = [];
        try {
            $uuid = $childEntry4['data']['id'];
            $ownerFormRef = $childEntry4['data']['attributes']['form']['ref'];
            $inputs = $projectDefinition['data']['project']['forms'][4]['inputs'];

            $branches = [];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branches[] = $input;
                }
            }

            $branchEntry = $entryGenerator->createBranchEntryPayload(
                $ownerFormRef,
                $branches[0]['branch'],
                $uuid,
                $branches[0]['ref']
            );

            //post the branch entry
            //perform an app upload (auth via JWT)
            Auth::guard('api_external')->login($user);
            $response[] = $this->actingAs($user)->post($this->endpoint . $project->slug, $branchEntry);
            $response[0]->assertStatus(200)
                ->assertExactJson([
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(
                1,
                BranchEntry::where(
                    'form_ref', $ownerFormRef)
                    //   ->where('owner_uuid', $uuid)
                    ->get());
            $this->assertCount(
                5,
                BranchEntry::where(
                    'project_id', $project->id)
                    //   ->where('owner_uuid', $uuid)
                    ->get());

            $branchCounts = json_decode(Entry::where('uuid', $uuid)
                ->value('branch_counts'), true);

            $this->assertEquals([
                $branches[0]['ref'] => 1,
                $branches[1]['ref'] => 0,//branch was deleted
            ], $branchCounts);

            //entry should be assigned to currently logged-in user
            $this->assertEquals($user->id, BranchEntry::where('uuid', $branchEntry['data']['id'])->value('user_id'));

            //assert payload stored vs. payload uploaded
            $branchEntryFromDB = BranchEntry::where('uuid', $branchEntry['data']['id'])->first();
            $this->assertEquals($user->id, $branchEntryFromDB->user_id);
            $entryFromPayload = $branchEntry['data']['branch_entry'];
            $this->assertBranchEntryStoredAgainstBranchEntryPayload(
                $branchEntryFromDB,
                $entryFromPayload,
                $projectDefinition,
                $branches[0]['ref'],
                4
            );


            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
        } catch (Exception $e) {
            $this->clearDatabase([
                'user' => $user,
                'project' => $project
            ]);
            $this->logTestError($e, $response);
        }
    }
}