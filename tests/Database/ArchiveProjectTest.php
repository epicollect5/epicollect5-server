<?php

namespace Tests\ArchiveEntriesTest;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectArchive;

class ArchiveProjectTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function it_archives_project()
    {
        $repeatCount = 100; // Number of times to repeat the test case

        for ($i = 0; $i < $repeatCount; $i++) {
            // Create a test project
            $project = factory(Project::class)->create();
            //assert project is present before archiving
            $this->assertEquals(1, Project::where('id', $project->id)->count());
            // Run the archiveProject function
            $result = $this->app->call('ec5\Http\Controllers\ProjectControllerBase@archiveProject', [
                'projectId' => $project->id,
                'projectSlug' => $project->slug
            ]);
            // Assert that the function returned true
            $this->assertTrue($result);
            //assert project has been moved
            $this->assertEquals(0, Project::where('id', $project->id)->count());
            $this->assertEquals(1, ProjectArchive::where('id', $project->id)->count());
        }
    }
}
