<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal;

use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Throwable;

/* This class does not follow optimal testing practices
   since the application does not get rebooted before each request
   like a production environment,
   but these tests are still useful to find bugs when uploading entries

   be aware __construct() are called only the first time,
   so it might have some false positives or not detect
   some errors

   imp: project stats are not updated per each upload
   since that is expensive, we update them when the
   project home page is requested, or the dataviewer

*/

class UploadWebControllerBulkUploadsTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private User $user;
    private array $projectDefinition;
    private Project $project;
    private EntryGenerator $entryGenerator;
    private string $endpoint = 'api/internal/bulk-upload/';

    public function setUp(): void
    {
        parent::setUp();
        $user = factory(User::class)->create();
        $projectDefinition = ProjectDefinitionGenerator::createProject(5);
        //enable bulk uploads for members
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private'),
                'can_bulk_upload' => config('epicollect.strings.can_bulk_upload.members'),
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
            $this->entryGenerator = $entryGenerator;

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_should_not_touch_created_at_on_bulk_upload()
    {
        $response = [];
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //generate a fake entry for the top parent form
            $entry = $this->entryGenerator->createParentEntryPayload($formRef);

            //save the entry to the DB
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                config('epicollect.strings.project_roles.creator'),
                $this->projectDefinition,
                $entry
            );

            $createdAtBefore = $entryRowBundle['entryStructure']->getEntryCreatedAt();

            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/bulk-upload/' . $this->project->slug, $entry);
            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertEquals(0, Entry::where('project_id', $this->project->id)->value('child_counts'));
            $this->assertEquals(0, Entry::where('project_id', $this->project->id)->value('branch_counts'));
            $this->assertCount(
                1,
                Entry::where('project_id', $this->project->id)
                    ->where('uuid', $entry['data']['id'])
                    ->get()
            );

            $createdAtAfter = Entry::where('uuid', $entry['data']['id'])->value('created_at');
            $this->assertEquals(
                $createdAtBefore,
                $createdAtAfter->format('Y-m-d\TH:i:s.000\Z')
            );
        } catch (Throwable $e) {
            //dd($e->getMessage(), $response, json_encode($entry), json_encode($projectDefinition));
            $this->logTestError($e, $response);
        }
    }

    public function test_should_not_touch_created_at_on_bulk_upload_loop()
    {
        $entriesCount = rand(2, 10);
        for ($i = 0; $i < $entriesCount; $i++) {
            $this->test_should_not_touch_created_at_on_bulk_upload();
        }
    }

    public function test_should_not_touch_entry_user_id_on_bulk_upload()
    {
        //create a fake user
        $collector = factory(User::class)->create();
        $response = [];
        try {
            //get top parent formRef
            $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
            //generate a fake entry for the top parent form
            $entry = $this->entryGenerator->createParentEntryPayload($formRef);

            //save the entry to the DB
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $collector,
                $this->project,
                config('epicollect.strings.project_roles.collector'),
                $this->projectDefinition,
                $entry
            );

            $userIdBefore = $entryRowBundle['entryStructure']->getUserId();

            //perform a web upload
            $response[] = $this->actingAs($this->user)->post('api/internal/bulk-upload/' . $this->project->slug, $entry);
            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertEquals(0, Entry::where('project_id', $this->project->id)->value('child_counts'));
            $this->assertEquals(0, Entry::where('project_id', $this->project->id)->value('branch_counts'));
            $this->assertCount(
                1,
                Entry::where('project_id', $this->project->id)
                    ->where('uuid', $entry['data']['id'])
                    ->get()
            );

            $userIdAfter = Entry::where('uuid', $entry['data']['id'])->value('user_id');
            $this->assertEquals(
                $userIdBefore,
                $userIdAfter
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    //branches

    /**
     * @throws Throwable
     */
    public function test_should_not_touch_created_at_on_bulk_upload_branch()
    {
        //generate a parent entry (form 0)
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                config('epicollect.strings.project_roles.creator'),
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );
        $parentEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];

        $branches = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branches[] = $input;
            }
        }

        //generate a branch entry for the first branch (index 0)
        $branchRef = $branches[0]['ref'];
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branches[0]['branch'],
                $parentEntryFromDB->uuid,
                $branchRef
            );
            $entryRowBundle = $this->entryGenerator->createBranchEntryRow(
                $this->user,
                $this->project,
                config('epicollect.strings.project_roles.creator'),
                $this->projectDefinition,
                $branchEntryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $branchEntryPayloads[$i]
            );
        }

        //assert row is created
        $this->assertCount(
            1,
            BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->get()
        );
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
        $createdAtBefore = $branchEntryFromDB->created_at->format('Y-m-d\TH:i:s.000\Z');

        //assert response passing the form ref and the branch ref
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post('api/internal/bulk-upload/' . $this->project->slug, $branchEntryPayloads[0]);
            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(
                1,
                BranchEntry::where('project_id', $this->project->id)
                    ->where('uuid', $branchEntryPayloads[0]['data']['id'])
                    ->get()
            );

            $createdAtAfter = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->value('created_at');
            $this->assertEquals(
                $createdAtBefore,
                $createdAtAfter->format('Y-m-d\TH:i:s.000\Z')
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_should_not_touch_user_id_on_bulk_upload_branch()
    {
        //create a fake user
        $collector = factory(User::class)->create();

        //generate a parent entry (form 0)
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $collector,
                $this->project,
                config('epicollect.strings.project_roles.collector'),
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $entryPayloads[0]['data']['id'])->get()
        );
        $parentEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];

        $branches = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branches[] = $input;
            }
        }

        //generate a branch entry for the first branch (index 0)
        $branchRef = $branches[0]['ref'];
        $branchEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                $formRef,
                $branches[0]['branch'],
                $parentEntryFromDB->uuid,
                $branchRef
            );
            $entryRowBundle = $this->entryGenerator->createBranchEntryRow(
                $collector,
                $this->project,
                config('epicollect.strings.project_roles.collector'),
                $this->projectDefinition,
                $branchEntryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $branchEntryPayloads[$i]
            );
        }

        //assert row is created
        $this->assertCount(
            1,
            BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->get()
        );
        $branchEntryFromDB = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->first();
        $userIdBefore = $branchEntryFromDB->user_id;

        //assert response passing the form ref and the branch ref
        $response = [];
        try {
            //CREATOR bulk upload overriding COLLECTOR entry, but user_is not touched
            $response[] = $this->actingAs($this->user)->post('api/internal/bulk-upload/' . $this->project->slug, $branchEntryPayloads[0]);
            $response[0]->assertStatus(200)
                ->assertExactJson(
                    [
                        "data" => [
                            "code" => "ec5_237",
                            "title" => "Entry successfully uploaded."
                        ]
                    ]
                );
            $this->assertCount(
                1,
                BranchEntry::where('project_id', $this->project->id)
                    ->where('uuid', $branchEntryPayloads[0]['data']['id'])
                    ->get()
            );

            $userIdAfter = BranchEntry::where('uuid', $branchEntryPayloads[0]['data']['id'])->value('user_id');
            $this->assertEquals(
                $userIdBefore,
                $userIdAfter
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }
}
