<?php

namespace Tests\Traits\Eloquent;

use ec5\Models\Eloquent\OAuthClientProjects;
use ec5\Models\Eloquent\ProjectStats;
use ec5\Models\Eloquent\ProjectStructure;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\User;
use Faker\Factory as Faker;

class RemoveProjectTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function it_removes_project()
    {
        $repeatCount = 100; // Number of times to repeat the test case

        for ($i = 0; $i < $repeatCount; $i++) {
            // Create a test project
            $project = factory(Project::class)->create([
                'name' => 'EC5 Unit Test ' . $i,
                'slug' => 'ec5-unit-test-' . $i,
                'created_by' => User::where('email', config('testing.SUPER_ADMIN_EMAIL'))->first()['id']
            ]);

            //add fake stats
            factory(ProjectStats::class)->create([
                'project_id' => $project->id,
                'total_entries' => 0
            ]);
            //fake structure
            factory(ProjectStructure::class)->create(
                ['project_id' => $project->id]
            );

            //fake app
            factory(OAuthClientProjects::class)->create(
                ['project_id' => $project->id]
            );

            //assert project is present before removing
            $this->assertEquals(1, Project::where('id', $project->id)->count());
            // Run the removeProject function
            $result = $this->app->call('ec5\Http\Controllers\Api\Auth\AccountController@removeProject', [
                'projectId' => $project->id,
                'projectSlug' => $project->slug
            ]);
            // Assert that the function returned true
            $this->assertTrue($result);
            //assert project is removed
            $this->assertEquals(0, Project::where('id', $project->id)
                ->count());
            //assert stats are dropped
            $this->assertEquals(0, ProjectStats::where('project_id', $project->id)
                ->count());
            //assert structure is dropped
            $this->assertEquals(0, ProjectStructure::where('project_id', $project->id)
                ->count());
            //assert app clients are dropped
            $this->assertEquals(0, OAuthClientProjects::where('project_id', $project->id)
                ->count());
        }
    }
}
