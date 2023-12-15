<?php

namespace Tests\Traits\Eloquent;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\User;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Config;

class ArchiveProjectTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_archives_projects()
    {
        $repeatCount = 25; // Number of times to repeat the test case
        // Create a Faker instance
        $faker = Faker::create();

        for ($i = 0; $i < $repeatCount; $i++) {
            // Create a test project
            $name = 'EC5 Unit Test ' . $i;
            $slug = Str::slug($name);
            $project = factory(Project::class)->create([
                'name' => $name,
                'slug' => $slug,
                'created_by' => User::where('email', config('testing.SUPER_ADMIN_EMAIL'))->first()['id']
            ]);
            //assert project is present before archiving
            $this->assertEquals(1, Project::where('id', $project->id)->count());
            // imp: run the archiveProject trait by calling a controller which uses it
            $result = $this->app->call('ec5\Http\Controllers\Web\Project\ProjectDeleteController@archiveProject', [
                'projectId' => $project->id,
                'projectSlug' => $project->slug
            ]);
            // Assert that the function returned true
            $this->assertTrue($result);
            //assert project is archived
            $this->assertEquals(1, Project::where('id', $project->id)
                ->where('status', 'archived')
                ->count());

            $this->assertEquals(0, Project::where('slug', $slug)
                ->count());

            $this->assertEquals(0, Project::where('name', $name)
                ->count());
        }
    }
}
