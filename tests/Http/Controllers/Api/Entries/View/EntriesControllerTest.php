<?php

namespace Tests\Http\Controllers\Api\Entries\View;

use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class EntriesControllerTest extends TestCase
{

    private $user;

    public function setUp()
    {
        parent::setUp();

        //create fake user for testing
        $user = factory(User::class)->create();
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
            'role' => config('epicollect.strings.project_roles.creator')
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

        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //Add mock entries & branch entries to mock project
        $entriesToArchive = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entriesToArchive as $entry) {
            factory(BranchEntry::class, $numOfBranchEntries)->create([
                'project_id' => $project->id,
                'form_ref' => $project->ref . '_' . uniqid(),
                'user_id' => $project->created_by,
                'owner_entry_id' => $entry->id //FK!
            ]);
        }

        $this->user = $user;
        $this->project = $project;

    }

    public function test_response_success()
    {

    }

}