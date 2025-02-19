<?php

namespace Tests\Http\Controllers\Api\Entries\View\External\PublicRoutes;

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
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ViewEntriesTitlesTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private $endpoint = 'api/entries/';

    public function setUp(): void
    {
        parent::setUp();
        //remove leftovers
        User::where(
            'email',
            'like',
            '%example.net%'
        )
            ->delete();

        $this->faker = Faker::create();

        //create fake user for testing
        $user = factory(User::class)->create();
        $role = config('epicollect.strings.project_roles.creator');

        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(5, true);
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
            'role' => $role
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

        $this->entryGenerator = new EntryGenerator($projectDefinition);
        $this->user = $user;
        $this->role = $role;
        $this->project = $project;
        $this->projectDefinition = $projectDefinition;
        $this->projectExtra = $projectExtra;
    }


    public function test_entries_public_endpoint_form_0_single_entry()
    {
        //generate entry
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
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

        $entryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //assert response passing parent form ref
        $queryString = '?form_ref=' . $formRef;
        $response = [];
        try {
            $response[] = $this
                ->get($this->endpoint . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesResponse($response[0]);
            $json = json_decode($response[0]->getContent(), true);
            $entryFromResponse = $json['data']['entries'][0];

            $this->assertEquals($entryFromDB->uuid, $entryFromResponse['id']);
            $this->assertEquals($entryFromDB->uuid, $entryFromResponse['entry']['entry_uuid']);
            $this->assertEquals($entryFromDB->title, $entryFromResponse['entry']['title']);
            $this->assertNotEquals($entryFromDB->title, $entryFromResponse['entry']['entry_uuid']);
            //timestamp
            $this->assertEquals(
                str_replace(' ', 'T', $entryFromDB->created_at) . '.000Z',
                $entryFromResponse['entry']['created_at']
            );
            //answers
            $entryFromDBEntryData = json_decode($entryFromDB->entry_data, true);
            $this->assertEquals($entryFromDBEntryData['entry']['answers'], $entryFromResponse['entry']['answers']);
            //project version
            $this->assertEquals(Project::version($this->project->slug), $entryFromResponse['entry']['project_version']);
            //user id
            $this->assertEquals($entryFromDB->user_id, $entryFromResponse['relationships']['user']['data']['id']);
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_entries_public_endpoint_child_form_1_single_entry()
    {
        //set the project as public
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();
        //generate a parent entry (form 0)
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
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

        //generate a child entry for this parent
        $childFormRef = array_get($this->projectDefinition, 'data.project.forms.1.ref');
        $childEntryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $childEntryPayloads[$i] = $this->entryGenerator->createChildEntryPayload($childFormRef, $formRef, $parentEntryFromDB->uuid);
            $entryRowBundle = $this->entryGenerator->createChildEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $childEntryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $childEntryPayloads[$i]
            );
        }

        //assert row is created
        $this->assertCount(
            1,
            Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->get()
        );
        $childEntryFromDB = Entry::where('uuid', $childEntryPayloads[0]['data']['id'])->first();

        //assert response passing the child form ref
        $queryString = '?form_ref=' . $childFormRef;
        $response = [];
        try {
            $response[] = $this
                ->get($this->endpoint . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesResponse($response[0]);
            $json = json_decode($response[0]->getContent(), true);
            $entryFromResponse = $json['data']['entries'][0];

            $this->assertEquals($childEntryFromDB->uuid, $entryFromResponse['id']);
            $this->assertEquals($childEntryFromDB->uuid, $entryFromResponse['entry']['entry_uuid']);
            $this->assertEquals($childEntryFromDB->title, $entryFromResponse['entry']['title']);
            $this->assertNotEquals($childEntryFromDB->title, $entryFromResponse['entry']['entry_uuid']);

            //timestamp
            $this->assertEquals(
                str_replace(' ', 'T', $childEntryFromDB->created_at) . '.000Z',
                $entryFromResponse['entry']['created_at']
            );
            //answers
            $childEntryFromDBEntryData = json_decode($childEntryFromDB->entry_data, true);
            $this->assertEquals($childEntryFromDBEntryData['entry']['answers'], $entryFromResponse['entry']['answers']);
            //project version
            $this->assertEquals(Project::version($this->project->slug), $entryFromResponse['entry']['project_version']);
            //user id
            $this->assertEquals($childEntryFromDB->user_id, $entryFromResponse['relationships']['user']['data']['id']);

            //parent
            $this->assertEquals($childEntryFromDB->parent_uuid, $entryFromResponse['relationships']['parent']['data']['parent_entry_uuid']);
            $this->assertEquals($childEntryFromDB->parent_form_ref, $entryFromResponse['relationships']['parent']['data']['parent_form_ref']);
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    //branches
    public function test_entries_public_endpoint_branch_of_form_0_single_entry()
    {
        //set the project as public
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();
        //generate a parent entry (form 0)
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        for ($i = 0; $i < 1; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
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
                $this->role,
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

        //assert response passing the form ref and the branch ref
        $queryString = '?form_ref=' . $formRef . '&branch_ref=' . $branchRef;
        $response = [];
        try {
            $response[] = $this
                ->get($this->endpoint . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            // dd($response[0]);
            $this->assertEntriesResponse($response[0], true);
            $json = json_decode($response[0]->getContent(), true);
            $entryFromResponse = $json['data']['entries'][0];

            $this->assertEquals($branchEntryFromDB->uuid, $entryFromResponse['id']);
            $this->assertEquals($branchEntryFromDB->uuid, $entryFromResponse['branch_entry']['entry_uuid']);
            $this->assertEquals($branchEntryFromDB->title, $entryFromResponse['branch_entry']['title']);
            $this->assertNotEquals($branchEntryFromDB->title, $entryFromResponse['branch_entry']['entry_uuid']);

            //timestamp
            $this->assertEquals(
                str_replace(' ', 'T', $branchEntryFromDB->created_at) . '.000Z',
                $entryFromResponse['branch_entry']['created_at']
            );
            //answers
            $branchEntryFromDBEntryData = json_decode($branchEntryFromDB->entry_data, true);
            $this->assertEquals($branchEntryFromDBEntryData['branch_entry']['answers'], $entryFromResponse['branch_entry']['answers']);
            //project version
            $this->assertEquals(Project::version($this->project->slug), $entryFromResponse['branch_entry']['project_version']);
            //user id
            $this->assertEquals($branchEntryFromDB->user_id, $entryFromResponse['relationships']['user']['data']['id']);
            //owner entry
            $this->assertEquals($branchEntryFromDB->owner_uuid, $entryFromResponse['relationships']['branch']['data']['owner_entry_uuid']);
            $this->assertEquals($branchEntryFromDB->owner_input_ref, $entryFromResponse['relationships']['branch']['data']['owner_input_ref']);
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_branch_entries_internal_filter_by_title()
    {
        //set the project as public
        $this->project->access = config('epicollect.strings.project_access.public');
        $this->project->save();
        //generate some parent entries (form 0)
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayloads = [];
        $numOfEntries = rand(2, 5);
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayloads[$i]
            );

            $this->assertEntryRowAgainstPayload(
                $entryRowBundle,
                $entryPayloads[$i]
            );
        }

        //assert rows are created
        $this->assertCount(
            $numOfEntries,
            Entry::where('project_id', $this->project->id)->get()
        );

        //get one owner entry
        $ownerEntryFromDB = Entry::where('uuid', $entryPayloads[0]['data']['id'])->first();

        //get all entries
        $entriesFromDB = Entry::where('project_id', $this->project->id)->get();

        $inputs = $this->projectDefinition['data']['project']['forms'][0]['inputs'];
        $branches = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branches[] = $input;
            }
        }

        //generate some branch entries for all the branches
        $numOfBranchEntries = rand(2, 5);

        foreach ($entriesFromDB as $entryFromDB) {
            foreach ($branches as $branch) {
                $branchEntryPayloads = [];
                for ($i = 0; $i < $numOfBranchEntries; $i++) {
                    $branchEntryPayloads[$i] = $this->entryGenerator->createBranchEntryPayload(
                        $formRef,
                        $branch['branch'],
                        $entryFromDB->uuid,
                        $branch['ref']
                    );
                    $entryRowBundle = $this->entryGenerator->createBranchEntryRow(
                        $this->user,
                        $this->project,
                        $this->role,
                        $this->projectDefinition,
                        $branchEntryPayloads[$i]
                    );

                    $this->assertEntryRowAgainstPayload(
                        $entryRowBundle,
                        $branchEntryPayloads[$i]
                    );
                }
            }
        }

        //assert rows are created
        $this->assertCount(
            $numOfEntries,
            Entry::where('project_id', $this->project->id)->get()
        );
        $this->assertCount(
            $numOfEntries * $numOfBranchEntries * sizeof($branches),
            BranchEntry::where('project_id', $this->project->id)->get()
        );

        //assert response passing the form ref, the branch ref the owner entry uuid
        $queryString = '?form_ref=' . $formRef . '&branch_ref=' . $branches[0]['ref'] . '&branch_owner_uuid=' . $ownerEntryFromDB->uuid;

        $response = [];
        try {
            $response[] = $this
                ->get($this->endpoint . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);
            $this->assertEntriesResponse($response[0], true);
            $json = json_decode($response[0]->getContent(), true);
            $branchEntries = $json['data']['entries'];
            $this->assertCount(
                $numOfBranchEntries,
                $branchEntries
            );

            //check all the entries belong the owner entry
            foreach ($branchEntries as $branchEntry) {
                $this->assertEquals(
                    $ownerEntryFromDB->uuid,
                    $branchEntry['relationships']['branch']['data']['owner_entry_uuid']
                );

            }
        } catch (\Throwable $e) {
            $this->logTestError($e, $response);
        }
    }
}
