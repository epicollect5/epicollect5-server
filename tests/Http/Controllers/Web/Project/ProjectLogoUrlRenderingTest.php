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

        // Use shared generator for project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);

        $project = factory(Project::class)->create(
            array_merge([
                'created_by' => $creator->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.public'),
                'visibility' => config('epicollect.strings.project_visibility.listed'),
                'status' => config('epicollect.strings.project_status.active'),
                'logo_url' => 'logo.jpg' // Set logo_url to trigger media endpoint URL in templates
            ], $projectOverrides)
        );

        // Set up project structure with generated definition
        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'project_definition' => json_encode($projectDefinition['data'])
        ]);

        // Set up project stats
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
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
        $expectedTimestamp = (string)strtotime($project->structure_last_updated);

        $response = $this
            ->get('project/' . $project->slug)
            ->assertStatus(200)
            ->assertViewIs('project.project_home');

        // Assert the cache-busting v= parameter is present with correct timestamp
        $response->assertSee('v=' . $expectedTimestamp);
        // Assert the logo URL contains the project slug and format parameter
        $response->assertSee('api/internal/media/' . $project->slug);
        $response->assertSee('format=project_thumb');
    }

    /**
     * Test dataviewer view renders correctly
     * Uses: resources/views/project/dataviewer.blade.php
     */
    public function test_dataviewer_view_renders_with_structure_last_updated()
    {
        $data = $this->createProjectWithStructure();
        $project = $data['project'];
        $expectedTimestamp = (string)strtotime($project->structure_last_updated);

        $response = $this
            ->get('project/' . $project->slug . '/data')
            ->assertStatus(200)
            ->assertViewIs('project.dataviewer');

        // Assert the cache-busting v= parameter is present with correct timestamp
        $response->assertSee('v=' . $expectedTimestamp);
        // Assert the logo URL contains the project slug and format parameter
        $response->assertSee('api/internal/media/' . $project->slug);
        $response->assertSee('format=project_thumb');
    }

    /**
     * Test project_open view renders correctly
     * Uses: resources/views/meta/meta_project_open.blade.php
     */
    public function test_project_open_view_renders_with_structure_last_updated()
    {
        $data = $this->createProjectWithStructure();
        $project = $data['project'];
        $expectedTimestamp = (string)strtotime($project->structure_last_updated);

        $response = $this
            ->get('open/project/' . $project->slug)
            ->assertStatus(200)
            ->assertViewIs('project.project_open');

        // Assert the cache-busting v= parameter is present with correct timestamp
        $response->assertSee('v=' . $expectedTimestamp);
        // Assert the logo URL contains the project slug and format parameter
        $response->assertSee('api/internal/media/' . $project->slug);
        $response->assertSee('format=project_thumb');
    }

    /**
     * Test project_leave view renders correctly for non-creator
     * Uses: resources/views/project/project_leave.blade.php
     */
    public function test_project_leave_view_renders_with_structure_last_updated()
    {
        $creator = factory(User::class)->create();
        $user = factory(User::class)->create();

        // Use shared generator for project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);

        $project = factory(Project::class)->create([
            'created_by' => $creator->id,
            'name' => array_get($projectDefinition, 'data.project.name'),
            'slug' => array_get($projectDefinition, 'data.project.slug'),
            'ref' => array_get($projectDefinition, 'data.project.ref'),
            'logo_url' => 'logo.jpg'
        ]);

        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'project_definition' => json_encode($projectDefinition['data'])
        ]);

        factory(ProjectStats::class)->create(['project_id' => $project->id, 'total_entries' => 0]);

        // Reload project to get the updated structure_last_updated timestamp
        $project = $project->fresh();
        $expectedTimestamp = (string)strtotime($project->structure_last_updated);

        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/leave')
            ->assertStatus(200)
            ->assertViewIs('project.project_leave');

        // Assert the cache-busting v= parameter is present with correct timestamp
        $response->assertSee('v=' . $expectedTimestamp);
        // Assert the logo URL contains the project slug and format parameter
        $response->assertSee('api/internal/media/' . $project->slug);
        $response->assertSee('format=project_thumb');
    }

    /**
     * Test project_delete view renders correctly for creator
     * Uses: resources/views/project/project_delete.blade.php
     */
    public function test_project_delete_view_renders_with_structure_last_updated()
    {
        $creator = factory(User::class)->create();

        // Use shared generator for project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);

        $project = factory(Project::class)->create([
            'created_by' => $creator->id,
            'name' => array_get($projectDefinition, 'data.project.name'),
            'slug' => array_get($projectDefinition, 'data.project.slug'),
            'ref' => array_get($projectDefinition, 'data.project.ref'),
            'status' => config('epicollect.strings.project_status.trashed'),
            'logo_url' => 'logo.jpg'
        ]);

        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'project_definition' => json_encode($projectDefinition['data'])
        ]);

        factory(ProjectStats::class)->create(['project_id' => $project->id, 'total_entries' => 0]);

        // Reload project to get the updated structure_last_updated timestamp
        $project = $project->fresh();
        $expectedTimestamp = (string)strtotime($project->structure_last_updated);

        $response = $this
            ->actingAs($creator, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/delete')
            ->assertStatus(200)
            ->assertViewIs('project.project_delete');

        // Assert the cache-busting v= parameter is present with correct timestamp
        $response->assertSee('v=' . $expectedTimestamp);
        // Assert the logo URL contains the project slug and format parameter
        $response->assertSee('api/internal/media/' . $project->slug);
        $response->assertSee('format=project_thumb');
    }

    /**
     * Test project_delete_entries view renders correctly for locked project
     * Uses: resources/views/project/project_delete_entries.blade.php
     */
    public function test_project_delete_entries_view_renders_with_structure_last_updated()
    {
        $creator = factory(User::class)->create();

        // Use shared generator for project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);

        $project = factory(Project::class)->create([
            'created_by' => $creator->id,
            'name' => array_get($projectDefinition, 'data.project.name'),
            'slug' => array_get($projectDefinition, 'data.project.slug'),
            'ref' => array_get($projectDefinition, 'data.project.ref'),
            'status' => config('epicollect.strings.project_status.locked'),
            'logo_url' => 'logo.jpg'
        ]);

        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'project_definition' => json_encode($projectDefinition['data'])
        ]);

        factory(ProjectStats::class)->create(['project_id' => $project->id, 'total_entries' => 0]);

        // Reload project to get the updated structure_last_updated timestamp
        $project = $project->fresh();
        $expectedTimestamp = (string)strtotime($project->structure_last_updated);

        $response = $this
            ->actingAs($creator, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/delete-entries')
            ->assertStatus(200)
            ->assertViewIs('project.project_delete_entries');

        // Assert the cache-busting v= parameter is present with correct timestamp
        $response->assertSee('v=' . $expectedTimestamp);
        // Assert the logo URL contains the project slug and format parameter
        $response->assertSee('api/internal/media/' . $project->slug);
        $response->assertSee('format=project_thumb');
    }

    /**
     * Test project_details view renders correctly
     * Uses: resources/views/project/project_details_content.blade.php
     */
    public function test_project_details_view_renders_with_structure_last_updated()
    {
        $creator = factory(User::class)->create();

        $project = factory(Project::class)->create([
            'created_by' => $creator->id,
            'logo_url' => 'logo.jpg' // Set logo_url to trigger media endpoint URL
        ]);

        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        factory(ProjectStats::class)->create(['project_id' => $project->id, 'total_entries' => 0]);
        factory(ProjectStructure::class)->create(['project_id' => $project->id]);

        // Reload project to get the updated structure_last_updated timestamp
        $project = $project->fresh();
        $expectedTimestamp = (string)strtotime($project->structure_last_updated);

        $response = $this
            ->actingAs($creator, self::DRIVER)
            ->get('myprojects/' . $project->slug)
            ->assertStatus(200)
            ->assertViewIs('project.project_details');

        // Assert the cache-busting v= parameter is present with correct timestamp
        $response->assertSee('v=' . $expectedTimestamp);
        // Assert the logo URL contains the project slug and format parameter
        $response->assertSee('api/internal/media/' . $project->slug);
        $response->assertSee('format=project_thumb');
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
        $this->assertNotNull($projectData->project_definition_version);
        $this->assertNotEmpty($projectData->project_definition_version);
        $this->assertSame(
            (string)strtotime($projectData->project_definition_version),
            (string)strtotime($projectData->structure_last_updated)
        );
    }

    public function test_project_definition_version_is_populated_in_public_project_results()
    {
        $data = $this->createProjectWithStructure();
        $project = $data['project'];

        $projects = (new Project())->publicAndListed();
        $projectData = collect($projects->items())->firstWhere('slug', $project->slug);

        $this->assertNotNull($projectData);
        $this->assertNotEmpty($projectData->project_definition_version);
        $this->assertSame(
            (string)strtotime($projectData->structure_last_updated),
            $projectData->project_definition_version
        );
    }

}
