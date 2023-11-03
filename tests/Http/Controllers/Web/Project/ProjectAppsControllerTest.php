<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStat;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Users\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ProjectAppsControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    public function test_api_page_renders_correctly()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        $role = Config::get('ec5Strings.project_roles.creator');
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStat::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/apps')
            ->assertStatus(200);
    }
}