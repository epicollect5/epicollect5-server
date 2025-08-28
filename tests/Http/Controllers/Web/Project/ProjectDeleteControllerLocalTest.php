<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Models\OAuth\OAuthClientProject;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProjectDeleteControllerLocalTest extends TestCase
{
    use DatabaseTransactions;

    public const string DRIVER = 'web';

    public function setUp(): void
    {
        parent::setUp();
        $this->overrideStorageDriver('local');
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_hard_delete_with_local_storage_mocked()
    {
        Storage::fake('project');
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $trashedStatus = config('epicollect.strings.project_status.trashed');

        //get existing counts
        $projectsCount = Project::count();

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        //add a user provider
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        //create mock project with that user
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'status' => $trashedStatus
            ]
        );

        // Create a fake project logo file
        Storage::disk('project')->put($project->ref.'/logo.jpg', 'fake logo content');

        //assign the user to that project with the CREATOR role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //set up project stats and project structures
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
        ]);
        factory(ProjectStructure::class)->create([
            'project_id' => $project->id
        ]);
        factory(OAuthClientProject::class)->create([
            'project_id' => $project->id
        ]);

        // Verify logo exists before deletion
        $this->assertTrue(Storage::disk('project')->exists($project->ref.'/logo.jpg'));

        // Act: Simulate the execution of the hardDelete method
        $response = $this->actingAs($user, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token(),
                'project-name' => $project->name
            ]);

        //Check if the redirect is successful
        $response->assertRedirect('/myprojects');

        //Check if the project is deleted
        $this->assertEquals(0, Project::where('id', $project->id)->count());

        //assert stats are dropped
        $this->assertEquals(0, ProjectStats::where('project_id', $project->id)->count());

        //assert structure is dropped
        $this->assertEquals(0, ProjectStructure::where('project_id', $project->id)->count());

        //assert app clients are dropped
        $this->assertEquals(0, OAuthClientProject::where('project_id', $project->id)->count());

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());

        // Verify logo file is deleted
        $this->assertFalse(Storage::disk('project')->exists($project->ref));

        // You can also check for messages in the session
        $response->assertSessionHas('message', 'ec5_114');

        //check counts - project should be deleted, so count stays the same
        $this->assertEquals($projectsCount, Project::count());
    }

    public function test_hard_delete_with_local_storage_real()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $trashedStatus = config('epicollect.strings.project_status.trashed');

        //get existing counts
        $projectsCount = Project::count();

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        //add a user provider
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        //create mock project with that user
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'status' => $trashedStatus
            ]
        );

        // Create a fake project logo file
        Storage::disk('project')->put($project->ref, 'fake logo content');

        //assign the user to that project with the CREATOR role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //set up project stats and project structures
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
        ]);
        factory(ProjectStructure::class)->create([
            'project_id' => $project->id
        ]);
        factory(OAuthClientProject::class)->create([
            'project_id' => $project->id
        ]);

        // Verify logo exists before deletion
        $this->assertTrue(Storage::disk('project')->exists($project->ref));

        // Act: Simulate the execution of the hardDelete method
        $response = $this->actingAs($user, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token(),
                'project-name' => $project->name
            ]);

        //Check if the redirect is successful
        $response->assertRedirect('/myprojects');

        //Check if the project is deleted
        $this->assertEquals(0, Project::where('id', $project->id)->count());

        //assert stats are dropped
        $this->assertEquals(0, ProjectStats::where('project_id', $project->id)->count());

        //assert structure is dropped
        $this->assertEquals(0, ProjectStructure::where('project_id', $project->id)->count());

        //assert app clients are dropped
        $this->assertEquals(0, OAuthClientProject::where('project_id', $project->id)->count());

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());

        // Verify logo file is deleted
        $this->assertFalse(Storage::disk('project')->exists('project_thumb/' .$project->ref));

        // You can also check for messages in the session
        $response->assertSessionHas('message', 'ec5_114');

        //check counts - project should be deleted, so count stays the same
        $this->assertEquals($projectsCount, Project::count());
    }
}
