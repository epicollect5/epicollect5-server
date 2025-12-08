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
use Aws\S3\Exception\S3Exception;
use Aws\Command;
use GuzzleHttp\Psr7\Response;

class ProjectDeleteControllerS3Test extends TestCase
{
    use DatabaseTransactions;

    public const string DRIVER = 'web';

    public function setUp(): void
    {
        parent::setUp();
        $this->overrideStorageDriver('s3');
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_hard_delete_with_s3_storage_success_mocked()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $trashedStatus = config('epicollect.strings.project_status.trashed');

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        //create mock project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'status' => $trashedStatus
        ]);

        // Mock S3 storage for successful deletion
        Storage::shouldReceive('disk')
            ->with('project')
            ->once()
            ->andReturnSelf();
        Storage::shouldReceive('deleteDirectory')
            ->with($project->ref)
            ->once()
            ->andReturn(true);

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

        // You can also check for messages in the session
        $response->assertSessionHas('message', 'ec5_114');
    }

    public function test_hard_delete_with_s3_storage_success_real_bucket()
    {
        $role = config('epicollect.strings.project_roles.creator');
        $trashedStatus = config('epicollect.strings.project_status.trashed');

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        //create mock project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'status' => $trashedStatus
        ]);

        // assign the user to that project with the CREATOR role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        // set up project stats and project structures
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

        // --- Arrange: Put a fake file on S3 for this project ---
        $disk = Storage::disk('project');
        $filePath = $project->ref . '/logo.jpg';
        $disk->put($filePath, 'dummy content');

        // Sanity check: ensure the file exists before delete
        $this->assertTrue($disk->exists($filePath));

        // Act: Simulate the execution of the hardDelete method
        $response = $this->actingAs($user, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token(),
                'project-name' => $project->name
            ]);

        // Assert: redirect worked
        $response->assertRedirect('/myprojects');

        // Project is deleted from DB
        $this->assertEquals(0, Project::where('id', $project->id)->count());

        // Assert: Project directory no longer exists in S3
        $this->assertFalse($disk->exists($filePath));

        // Assert: session message
        $response->assertSessionHas('message', 'ec5_114');
    }


    public function test_hard_delete_handles_s3_429_error_with_retry()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $trashedStatus = config('epicollect.strings.project_status.trashed');

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        //create mock project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'status' => $trashedStatus
        ]);

        // Mock S3 storage to throw retryable error then succeed
        Storage::shouldReceive('disk')
            ->with('project')
            ->times(4) // 1 initial + 3 retries
            ->andReturnSelf();
        Storage::shouldReceive('deleteDirectory')
            ->times(3) // Fail 3 times
            ->andThrow(new S3Exception(
                'Too Many Requests',
                new Command('DeleteObject'),
                ['response' => new Response(429)]
            ));
        Storage::shouldReceive('deleteDirectory')
            ->once() // Succeed on 4th try
            ->andReturn(true);

        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
        ]);
        factory(ProjectStructure::class)->create([
            'project_id' => $project->id
        ]);

        // Act: Should succeed after retries
        $response = $this->actingAs($user, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token(),
                'project-name' => $project->name
            ]);

        $response->assertRedirect('/myprojects');
        $this->assertEquals(0, Project::where('id', $project->id)->count());
    }
}
