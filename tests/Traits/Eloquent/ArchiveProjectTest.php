<?php

namespace Tests\Traits\Eloquent;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\User;
use Faker\Factory as Faker;

class ArchiveProjectTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function it_archives_project()
    {
        $repeatCount = 100; // Number of times to repeat the test case
        // Create a Faker instance
        $faker = Faker::create();

        for ($i = 0; $i < $repeatCount; $i++) {
            // Create a test project
            $project = factory(Project::class)->create([
                'name' => 'EC5 Unit Test ' . $i,
                'slug' => 'ec5-unit-test-' . $i,
                'created_by' => User::where('email', env('SUPER_ADMIN_EMAIL'))->first()['id']
            ]);
            //assert project is present before archiving
            $this->assertEquals(1, Project::where('id', $project->id)->count());
            // Run the archiveProject function
            $result = $this->app->call('ec5\Http\Controllers\ProjectControllerBase@archiveProject', [
                'projectId' => $project->id,
                'projectSlug' => $project->slug
            ]);
            // Assert that the function returned true
            $this->assertTrue($result);
            //assert project is archived
            $this->assertEquals(1, Project::where('id', $project->id)
                ->where('status', 'archived')
                ->count());
        }
    }
}
