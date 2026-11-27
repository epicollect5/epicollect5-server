<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProjectDataControllerPwaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        \URL::forceRootUrl('http://localhost');
    }

    public function test_add_entry_renders_pwa_when_enabled(): void
    {
        config()->set('epicollect.setup.pwa_enabled', true);
        $user = factory(User::class)->create();
        $project = $this->createProjectForUser($user);

        $response = $this->actingAs($user)->get('/project/' . $project->slug . '/add-entry');

        $response->assertStatus(200);
        $response->assertViewIs('project.pwa');
    }

    public function test_add_entry_renders_legacy_data_editor_when_disabled(): void
    {
        config()->set('epicollect.setup.pwa_enabled', false);
        $user = factory(User::class)->create();
        $project = $this->createProjectForUser($user);

        $response = $this->actingAs($user)->get('/project/' . $project->slug . '/add-entry');

        $response->assertStatus(200);
        $response->assertViewIs('project.data_editor');
    }

    public function test_edit_entry_renders_pwa_when_enabled(): void
    {
        config()->set('epicollect.setup.pwa_enabled', true);
        $user = factory(User::class)->create();
        $project = $this->createProjectForUser($user);

        $response = $this->actingAs($user)->get('/project/' . $project->slug . '/edit-entry');

        $response->assertStatus(200);
        $response->assertViewIs('project.pwa');
    }

    public function test_edit_entry_renders_legacy_data_editor_when_disabled(): void
    {
        config()->set('epicollect.setup.pwa_enabled', false);
        $user = factory(User::class)->create();
        $project = $this->createProjectForUser($user);

        $response = $this->actingAs($user)->get('/project/' . $project->slug . '/edit-entry');

        $response->assertStatus(200);
        $response->assertViewIs('project.data_editor');
    }

    private function createProjectForUser(User $user): Project
    {
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $project = factory(Project::class)->create(['access' => config('epicollect.strings.project_access.private')]);
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.collector'),
        ]);
        factory(ProjectStats::class)->create(['project_id' => $project->id]);
        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'project_definition' => json_encode($projectDefinition['data']),
        ]);

        return $project;
    }
}
