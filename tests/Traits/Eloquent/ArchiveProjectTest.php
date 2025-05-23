<?php

namespace Tests\Traits\Eloquent;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\User\User;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchiveProjectTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_archives_projects()
    {
        $repeatCount = 25; // Number of times to repeat the test case
        // Create a Faker instance
        $faker = Faker::create();

        //create fake users
        for ($i = 0; $i < 100; $i++) {
            $users[] = factory(User::class)->create();
        }

        for ($i = 0; $i < $repeatCount; $i++) {

            $userId = $users[rand(0, count($users) - 1)]->id;
            // Create a test project
            $name = 'EC5 Unit Test ' . $i;
            $slug = Str::slug($name);
            $project = factory(Project::class)->create([
                'name' => $name,
                'slug' => $slug,
                'created_by' => $userId
            ]);

            //assign user as creator
            factory(ProjectRole::class)->create([
                'project_id' => $project->id,
                'user_id' => $userId,
                'role' => config('epicollect.strings.project_roles.creator')
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

            //assert roles are dropped
            $this->assertEquals(0, ProjectRole::where('project_id', $project->id)
                ->count());
        }
    }
}
