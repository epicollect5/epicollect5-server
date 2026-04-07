<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tests for Blade templates that render project logo URLs with structure_last_updated cache versioning.
 *
 * These tests ensure that all views using $requestAttributes->requestedProject->structure_last_updated
 * in logo URLs render correctly without throwing errors.
 */
class ProjectLogoUrlRenderingTest extends TestCase
{
    use DatabaseTransactions;

    public const string DRIVER = 'web';

    public function setUp(): void
    {
        parent::setUp();
    }

    private function createProjectWithStructure(array $projectOverrides = []): array
    {
        $creator = factory(User::class)->create();

        $project = factory(Project::class)->create(
            array_merge([
                'created_by' => $creator->id,
                'access' => config('epicollect.strings.project_access.public'),
                'visibility' => config('epicollect.strings.project_visibility.listed'),
                'status' => config('epicollect.strings.project_status.active')
            ], $projectOverrides)
        );

        // Set up project stats and project structures
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
        ]);

        factory(ProjectStructure::class)->create([
            'project_id' => $project->id
        ]);

        return ['creator' => $creator, 'project' => $project];
    }

    /**
     * Test project_home view renders correctly
     * Uses: resources/views/project/project_home.blade.php
     */
    public function test_project_home_view_renders_with_structure_last_updated()
    {
        $data = $this->createProjectWithStructure();
        $project = $data['project'];

        $response = $this
            ->get('project/' . $project->slug)
            ->assertStatus(200)
            ->assertViewIs('project.project_home');
    }

    /**
     * Test dataviewer view renders correctly
     * Uses: resources/views/project/dataviewer.blade.php
     */
    public function test_dataviewer_view_renders_with_structure_last_updated()
    {
        $data = $this->createProjectWithStructure();
        $project = $data['project'];

        $response = $this
            ->get('project/' . $project->slug . '/data')
            ->assertStatus(200)
            ->assertViewIs('project.dataviewer');
    }

    /**
     * Test project_open view renders correctly
     * Uses: resources/views/meta/meta_project_open.blade.php
     */
    public function test_project_open_view_renders_with_structure_last_updated()
    {
        $data = $this->createProjectWithStructure();
        $project = $data['project'];

        $response = $this
            ->get('open/project/' . $project->slug)
            ->assertStatus(200)
            ->assertViewIs('project.project_open');
    }

    /**
     * Test project_leave view renders correctly for non-creator
     * Uses: resources/views/project/project_leave.blade.php
     */
    public function test_project_leave_view_renders_with_structure_last_updated()
    {
        $creator = factory(User::class)->create();
        $user = factory(User::class)->create();

        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        factory(ProjectStats::class)->create(['project_id' => $project->id, 'total_entries' => 0]);
        factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/leave')
            ->assertStatus(200)
            ->assertViewIs('project.project_leave');
    }

    /**
     * Test project_delete view renders correctly for creator
     * Uses: resources/views/project/project_delete.blade.php
     */
    public function test_project_delete_view_renders_with_structure_last_updated()
    {
        $creator = factory(User::class)->create();

        $project = factory(Project::class)->create([
            'created_by' => $creator->id,
            'status' => config('epicollect.strings.project_status.trashed')
        ]);

        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStats::class)->create(['project_id' => $project->id, 'total_entries' => 0]);
        factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        $response = $this
            ->actingAs($creator, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/delete')
            ->assertStatus(200)
            ->assertViewIs('project.project_delete');
    }

    /**
     * Test project_delete_entries view renders correctly for locked project
     * Uses: resources/views/project/project_delete_entries.blade.php
     */
    public function test_project_delete_entries_view_renders_with_structure_last_updated()
    {
        $creator = factory(User::class)->create();

        $project = factory(Project::class)->create([
            'created_by' => $creator->id,
            'status' => config('epicollect.strings.project_status.locked')
        ]);

        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStats::class)->create(['project_id' => $project->id, 'total_entries' => 0]);
        factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        $response = $this
            ->actingAs($creator, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/delete-entries')
            ->assertStatus(200)
            ->assertViewIs('project.project_delete_entries');
    }

    /**
     * Test project_details view renders correctly
     * Uses: resources/views/project/project_details_content.blade.php
     */
    public function test_project_details_view_renders_with_structure_last_updated()
    {
        $creator = factory(User::class)->create();

        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStats::class)->create(['project_id' => $project->id, 'total_entries' => 0]);
        factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        $response = $this
            ->actingAs($creator, self::DRIVER)
            ->get('myprojects/' . $project->slug)
            ->assertStatus(200)
            ->assertViewIs('project.project_details');
    }

    /**
     * Test that structure_last_updated is properly populated in ProjectDTO
     */
    public function test_structure_last_updated_is_populated_in_project_dto()
    {
        $data = $this->createProjectWithStructure();
        $project = $data['project'];

        $projectData = Project::findBySlug($project->slug);

        $this->assertNotNull($projectData->structure_last_updated);
        $this->assertNotEmpty($projectData->structure_last_updated);
        $this->assertTrue(strtotime($projectData->structure_last_updated) !== false);
    }

}
