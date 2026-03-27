<?php

namespace Tests\Traits\Eloquent;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Counters\EntryCounter;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\User\User;
use ec5\Services\Mapping\ProjectMappingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EntryCounterTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Project $project;
    protected string $formRef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->project = factory(Project::class)->create(['created_by' => $this->user->id]);
        $this->formRef = Generators::formRef($this->project->ref);
    }

    public function test_child_counts_updated_and_branch_counts_json_empty_when_no_branches()
    {
        // create parent entry
        $parent = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->formRef,
            'uuid' => uniqid('parent_'),
            'child_counts' => 0,
            'branch_counts' => json_encode([]),
        ]);

        // create 3 child entries
        for ($i = 0; $i < 3; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'form_ref' => $this->formRef,
                'parent_uuid' => $parent->uuid,
            ]);
        }

        // Build ProjectDTO with form_counts > 1 and no branches
        $projectDto = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $projectDto->setId($this->project->id);
        $projectDto->addProjectStats(['form_counts' => ['a', 'b'], 'branch_counts' => []]);

        // Build EntryStructureDTO
        $payload = [
            'type' => 'entry',
            'id' => $parent->uuid,
            'attributes' => [
                'form' => ['ref' => $this->formRef]
            ],
            'entry' => ['entry_uuid' => $parent->uuid]
        ];
        $entryStructure = new EntryStructureDTO();
        $entryStructure->createStructure($payload);
        $entryStructure->setProjectId($this->project->id);

        $counter = new EntryCounter();
        $counter->updateEntryCounts($projectDto, $entryStructure);

        $row = Entry::where('uuid', $parent->uuid)->first();
        $this->assertEquals(3, (int) $row->child_counts);
        $this->assertEquals([], json_decode($row->branch_counts, true));
    }

    public function test_branch_counts_updated_when_branches_exist_and_single_form()
    {
        // create entry
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->formRef,
            'uuid' => uniqid('owner_'),
            'branch_counts' => json_encode([]),
        ]);

        // create 2 branch entries for owner_input_ref 'b1'
        for ($i = 0; $i < 2; $i++) {
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'owner_entry_id' => $entry->id,
                'owner_uuid' => $entry->uuid,
                'owner_input_ref' => 'b1',
                'form_ref' => $this->formRef,
            ]);
        }

        // Build ProjectDTO with single form and branches defined in ProjectExtra
        $projectDto = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $projectDto->setId($this->project->id);
        $projectDto->addProjectStats(['form_counts' => ['only'], 'branch_counts' => ['b1' => 0]]);
        // register a branch in project extra
        $projectDto->addProjectExtra(['forms' => [$this->formRef => ['branch' => ['b1' => []]]]]);

        $payload = [
            'type' => 'entry',
            'id' => $entry->uuid,
            'attributes' => ['form' => ['ref' => $this->formRef]],
            'entry' => ['entry_uuid' => $entry->uuid]
        ];
        $entryStructure = new EntryStructureDTO();
        $entryStructure->createStructure($payload);
        $entryStructure->setProjectId($this->project->id);

        $counter = new EntryCounter();
        $counter->updateEntryCounts($projectDto, $entryStructure);

        $row = Entry::where('uuid', $entry->uuid)->first();
        $decoded = json_decode($row->branch_counts, true);
        $this->assertArrayHasKey('b1', $decoded);
        $this->assertEquals(2, (int) $decoded['b1']);
    }

    public function test_both_child_and_branch_counts_updated()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->formRef,
            'uuid' => uniqid('both_'),
            'child_counts' => 0,
            'branch_counts' => json_encode([]),
        ]);

        // children
        for ($i = 0; $i < 4; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'form_ref' => $this->formRef,
                'parent_uuid' => $entry->uuid,
            ]);
        }

        // branches
        for ($i = 0; $i < 3; $i++) {
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'owner_entry_id' => $entry->id,
                'owner_uuid' => $entry->uuid,
                'owner_input_ref' => 'bx',
                'form_ref' => $this->formRef,
            ]);
        }

        $projectDto = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $projectDto->setId($this->project->id);
        $projectDto->addProjectStats(['form_counts' => ['a', 'b'], 'branch_counts' => ['bx' => 0]]);
        $projectDto->addProjectExtra(['forms' => [$this->formRef => ['branch' => ['bx' => []]]]]);

        $payload = ['type' => 'entry', 'id' => $entry->uuid, 'attributes' => ['form' => ['ref' => $this->formRef]], 'entry' => ['entry_uuid' => $entry->uuid]];
        $entryStructure = new EntryStructureDTO();
        $entryStructure->createStructure($payload);
        $entryStructure->setProjectId($this->project->id);

        $counter = new EntryCounter();
        $counter->updateEntryCounts($projectDto, $entryStructure);

        $row = Entry::where('uuid', $entry->uuid)->first();
        $this->assertEquals(4, (int) $row->child_counts);
        $decoded = json_decode($row->branch_counts, true);
        $this->assertEquals(3, (int) $decoded['bx']);
    }

    public function test_no_update_when_no_children_and_no_branches()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'form_ref' => $this->formRef,
            'uuid' => uniqid('none_'),
            'child_counts' => 7,
            'branch_counts' => json_encode(['x' => 9]),
        ]);

        $projectDto = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $projectDto->setId($this->project->id);
        $projectDto->addProjectStats(['form_counts' => [], 'branch_counts' => []]);
        $projectDto->addProjectExtra(['forms' => []]);

        $payload = ['type' => 'entry', 'id' => $entry->uuid, 'attributes' => ['form' => ['ref' => $this->formRef]], 'entry' => ['entry_uuid' => $entry->uuid]];
        $entryStructure = new EntryStructureDTO();
        $entryStructure->createStructure($payload);
        $entryStructure->setProjectId($this->project->id);

        $counter = new EntryCounter();
        $counter->updateEntryCounts($projectDto, $entryStructure);

        $row = Entry::where('uuid', $entry->uuid)->first();
        // unchanged
        $this->assertEquals(7, (int) $row->child_counts);
        $this->assertEquals(['x' => 9], json_decode($row->branch_counts, true));
    }
}
