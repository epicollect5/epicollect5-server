<?php

namespace Http\Controllers\Api\Entries\View;

use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class ViewEntriesLocationsControllerTest extends TestCase
{
    use DatabaseTransactions, Assertions;

    private $user;
    private $project;
    private $role;
    private $projectDefinition;
    private $entryGenerator;
    private $entryPayload;
    private $entryStored;


    public function setUp()
    {
        parent::setUp();

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
                'access' => config('epicollect.strings.project_access.private')
            ]
        );
        //add role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //create basic project definition
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'])
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

    }

    public function test_parent_entry_row_stored_to_db()
    {
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');
        $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
        $entryRowBundle = $this->entryGenerator->createParentEntryRow(
            $this->user,
            $this->project,
            $this->role,
            $this->projectDefinition,
            $entryPayload
        );

        $this->assertEntryRow(
            $this->projectDefinition,
            $this->project,
            $entryRowBundle['entryStructure'],
            $entryPayload,
            $entryRowBundle['skippedInputRefs'],
            $formRef
        );

    }

    public function test_multiple_parent_entry_rows_stored_to_db()
    {
        $count = rand(500, 1000);
        for ($i = 0; $i < $count; $i++) {
            $this->test_parent_entry_row_stored_to_db();
        }
    }

    public function test_entries_locations_endpoint()
    {

        //generate entries
        $formRef = array_get($this->projectDefinition, 'data.project.forms.0.ref');

        $count = rand(5, 10);
        for ($i = 0; $i < $count; $i++) {
            $entryPayload = $this->entryGenerator->createParentEntryPayload($formRef);
            $entryRowBundle = $this->entryGenerator->createParentEntryRow(
                $this->user,
                $this->project,
                $this->role,
                $this->projectDefinition,
                $entryPayload
            );

            $this->assertEntryRow(
                $this->projectDefinition,
                $this->project,
                $entryRowBundle['entryStructure'],
                $entryPayload,
                $entryRowBundle['skippedInputRefs'],
                $formRef
            );
        }

        //assert rows are created
        $this->assertCount($count, Entry::where('project_id', $this->project->id)->get());

        //get location inputs
        $locationInputRefs = $this->getLocationInputRefs();

        //assert response
        $queryString = '?input_ref=' . $locationInputRefs[0];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->get('api/internal/entries-locations/' . $this->project->slug . $queryString);
            $response[0]->assertStatus(200);

            dd($response);
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function getLocationInputRefs()
    {
        $locationInputRefs = [];
        $inputs = array_get($this->projectDefinition, 'data.project.forms.0.inputs');
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
                $locationInputRefs[] = $input['ref'];
            }

            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
                        $locationInputRefs[] = $groupInput['ref'];
                    }
                }
            }
        }

        return $locationInputRefs;
    }
}