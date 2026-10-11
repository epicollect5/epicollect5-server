<?php

namespace Tests\Http\Controllers\Api\Entries\Upload\Internal\EditExistingEntries;

use Auth;
use DB;
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
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Throwable;

class RemoveLeftoverBranchEntriesTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private string $endpoint = 'api/internal/web-upload/';

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

        //create fake user for testing
        $user = factory(User::class)->create();
        $role = config('epicollect.strings.project_roles.creator');

        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);

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

    /**
     * When a parent entry is edited and a branch question is jumped/skipped,
     * any leftover branch entries for that owner must be removed. This also
     * covers the regression where the lookup was a full table scan.
     *
     * @throws Throwable
     */
    public function test_should_remove_leftover_branch_entries_when_branch_is_jumped_on_edit()
    {
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');

        //find a branch input ref on form 0
        $ownerInputRef = '';
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                $branchInputs = $input['branch'];
                break;
            }
        }
        $this->assertNotEmpty($ownerInputRef, 'Expected a branch input on form 0');

        //create a parent entry
        Auth::guard('api_internal')->login($this->user);
        $parentPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $parentPayload
        );
        $parentEntry = Entry::where('uuid', $parentPayload['data']['id'])->first();
        $this->assertNotNull($parentEntry);

        //create a branch entry owned by this parent
        $branchPayload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $parentEntry->uuid,
            $ownerInputRef
        );
        $this->entryGenerator->createBranchEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $branchPayload
        );
        $this->assertCount(
            1,
            BranchEntry::where('uuid', $branchPayload['data']['id'])->get()
        );

        //edit the parent entry, marking the branch input as jumped
        $editedPayload = $parentPayload;
        $editedAnswers = $editedPayload['data']['entry']['answers'];
        foreach ($editedAnswers as $ref => $answer) {
            if ($ref === $ownerInputRef) {
                $editedAnswers[$ref]['was_jumped'] = true;
                break;
            }
        }
        $editedPayload['data']['entry']['answers'] = $editedAnswers;

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $editedPayload);
            $response[0]->assertStatus(200);

            //the leftover branch entry should have been deleted
            $this->assertCount(
                0,
                BranchEntry::where('uuid', $branchPayload['data']['id'])->get()
            );
            //the parent entry must still exist
            $this->assertCount(
                1,
                Entry::where('uuid', $parentPayload['data']['id'])->get()
            );
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    /**
     * Guards the index contract: the leftover branch lookup must use the
     * composite index `branch_entries_optimized_search` (not a full scan).
     * This fails if the FORCE INDEX is removed or the index is dropped.
     */
    public function test_leftover_branch_entries_query_uses_optimized_search_index(): void
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');

        $query = BranchEntry::from(
            DB::raw(config('epicollect.tables.branch_entries') . ' FORCE INDEX (branch_entries_optimized_search)')
        )
            ->where('project_id', $this->project->id)
            ->where('form_ref', $formRef)
            ->where('owner_uuid', '00000000-0000-0000-0000-000000000000')
            ->whereIn('owner_input_ref', ['00000000000000000000000000000000000000000000000000000000000000']);

        $explain = DB::select('EXPLAIN ' . $query->toSql(), $query->getBindings());

        $this->assertEquals('branch_entries_optimized_search', $explain[0]->key);
        $this->assertNotEquals('ALL', $explain[0]->type);
    }

    /**
     * @throws Throwable
     */
    private function makeParentEntry($formRef): array
    {
        Auth::guard('api_internal')->login($this->user);
        $parentPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $parentPayload
        );
        return $parentPayload;
    }

    /**
     * @throws Throwable
     */
    private function makeBranchEntry($formRef, $branchInputs, $parentUuid, $ownerInputRef): string
    {
        $branchPayload = $this->entryGenerator->createBranchEntryPayload(
            $formRef,
            $branchInputs,
            $parentUuid,
            $ownerInputRef
        );
        $this->entryGenerator->createBranchEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $branchPayload
        );
        return $branchPayload['data']['id'];
    }

    private function editParentJumping($parentPayload, array $jumpRefs): void
    {
        $editedPayload = $parentPayload;
        $editedAnswers = $editedPayload['data']['entry']['answers'];
        foreach ($editedAnswers as $ref => $answer) {
            if (in_array($ref, $jumpRefs, true)) {
                $editedAnswers[$ref]['was_jumped'] = true;
            }
        }
        $editedPayload['data']['entry']['answers'] = $editedAnswers;
        $response = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $editedPayload);
        $response->assertStatus(200);
    }

    /**
     * @throws Throwable
     */
    public function test_should_remove_multiple_skipped_branch_entries()
    {
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');

        $branchDefs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $branchDefs[] = ['ref' => $input['ref'], 'branchInputs' => $input['branch']];
            }
        }
        $this->assertGreaterThanOrEqual(2, count($branchDefs), 'Expected at least 2 branch inputs on form 0');

        $parentPayload = $this->makeParentEntry($formRef);
        $parentEntry = Entry::where('uuid', $parentPayload['data']['id'])->first();
        $this->assertNotNull($parentEntry);

        $branchUuids = [];
        foreach ($branchDefs as $branchDef) {
            $branchUuids[$branchDef['ref']] = $this->makeBranchEntry(
                $formRef,
                $branchDef['branchInputs'],
                $parentEntry->uuid,
                $branchDef['ref']
            );
        }

        $jumpRefs = array_column($branchDefs, 'ref');
        $this->editParentJumping($parentPayload, $jumpRefs);

        foreach ($branchUuids as $uuid) {
            $this->assertCount(0, BranchEntry::where('uuid', $uuid)->get());
        }
        $this->assertCount(1, Entry::where('uuid', $parentPayload['data']['id'])->get());
    }

    /**
     * @throws Throwable
     */
    public function test_should_preserve_non_skipped_branch_entries()
    {
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');

        $branchDefs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $branchDefs[] = ['ref' => $input['ref'], 'branchInputs' => $input['branch']];
            }
        }
        $this->assertGreaterThanOrEqual(2, count($branchDefs), 'Expected at least 2 branch inputs on form 0');

        $parentPayload = $this->makeParentEntry($formRef);
        $parentEntry = Entry::where('uuid', $parentPayload['data']['id'])->first();

        $jumpedRef = $branchDefs[0]['ref'];
        $keptRef = $branchDefs[1]['ref'];
        $jumpedUuid = $this->makeBranchEntry($formRef, $branchDefs[0]['branchInputs'], $parentEntry->uuid, $jumpedRef);
        $keptUuid = $this->makeBranchEntry($formRef, $branchDefs[1]['branchInputs'], $parentEntry->uuid, $keptRef);

        $this->editParentJumping($parentPayload, [$jumpedRef]);

        $this->assertCount(0, BranchEntry::where('uuid', $jumpedUuid)->get());
        $this->assertCount(1, BranchEntry::where('uuid', $keptUuid)->get());
    }

    /**
     * @throws Throwable
     */
    public function test_branch_counts_updated_after_deletion()
    {
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');

        $branchDefs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $branchDefs[] = ['ref' => $input['ref'], 'branchInputs' => $input['branch']];
            }
        }
        $this->assertGreaterThanOrEqual(2, count($branchDefs));

        $parentPayload = $this->makeParentEntry($formRef);
        $parentEntry = Entry::where('uuid', $parentPayload['data']['id'])->first();

        $jumpedRef = $branchDefs[0]['ref'];
        $keptRef = $branchDefs[1]['ref'];
        $this->makeBranchEntry($formRef, $branchDefs[0]['branchInputs'], $parentEntry->uuid, $jumpedRef);
        $this->makeBranchEntry($formRef, $branchDefs[1]['branchInputs'], $parentEntry->uuid, $keptRef);

        $this->editParentJumping($parentPayload, [$jumpedRef]);

        $parentFromDb = Entry::where('uuid', $parentPayload['data']['id'])->first();
        $branchCounts = $parentFromDb->branch_counts;
        if (is_string($branchCounts)) {
            $branchCounts = json_decode($branchCounts, true);
        }
        $this->assertEquals(0, $branchCounts[$jumpedRef]);
        $this->assertEquals(1, $branchCounts[$keptRef]);
    }

    /**
     * @throws Throwable
     */
    public function test_should_delete_large_batch_in_chunks()
    {
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');

        $ownerInputRef = '';
        /** @noinspection PhpUnusedLocalVariableInspection */
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $ownerInputRef = $input['ref'];
                /** @noinspection PhpUnusedLocalVariableInspection */
                $branchInputs = $input['branch'];
                break;
            }
        }
        $this->assertNotEmpty($ownerInputRef);

        $parentPayload = $this->makeParentEntry($formRef);
        $parentEntry = Entry::where('uuid', $parentPayload['data']['id'])->first();

        $rows = [];
        for ($i = 0; $i < 600; $i++) {
            $rows[] = [
                'project_id' => $this->project->id,
                'uuid' => Uuid::uuid4()->toString(),
                'owner_entry_id' => $parentEntry->id,
                'owner_uuid' => $parentEntry->uuid,
                'owner_input_ref' => $ownerInputRef,
                'form_ref' => $formRef,
                'user_id' => $this->user->id,
                'platform' => 'WEB',
                'device_id' => '',
                'created_at' => now(),
                'uploaded_at' => now(),
                'title' => 'leftover',
                'entry_data' => json_encode([]),
                'geo_json_data' => json_encode([]),
            ];
        }
        DB::table(config('epicollect.tables.branch_entries'))->insert($rows);

        $this->assertCount(600, BranchEntry::where('owner_uuid', $parentEntry->uuid)->get());

        $this->editParentJumping($parentPayload, [$ownerInputRef]);

        $this->assertCount(0, BranchEntry::where('owner_uuid', $parentEntry->uuid)->get());
        $this->assertCount(1, Entry::where('uuid', $parentPayload['data']['id'])->get());
    }
}
