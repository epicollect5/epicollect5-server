<?php

namespace Tests\Models\Eloquent\Project;

use Tests\TestCase;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Users\User;
use Config;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MethodTransferOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    public function testTransferOwnership()
    {
        $creatorRole = Config::get('ec5Permissions.projects.creator_role');
        $managerRole = Config::get('ec5Permissions.projects.manager_role');

        //create a fake user with creator email
        $creator = factory(User::class)->create([
            'email' => Config::get('testing.CREATOR_EMAIL')
        ]);

        //create a fake user with manager email
        $manager = factory(User::class)->create([
            'email' => Config::get('testing.MANAGER_EMAIL')
        ]);

        //create fake project with creator user
        $project = factory(Project::class)->create(
            ['created_by' => $creator->id]
        );

        //add creator role to that project
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);

        //add manager role to that project
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => $managerRole
        ]);

        $this->assertDatabaseHas('users', ['email' => Config::get('testing.CREATOR_EMAIL')]);
        $this->assertDatabaseHas('users', ['email' => Config::get('testing.MANAGER_EMAIL')]);

        $this->assertDatabaseHas('project_roles', [
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => $managerRole
        ]);

        $this->assertDatabaseHas('project_roles', [
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);


        if ($project->transferOwnership($project->id, $creator->id, $manager->id)) {
            //assert creator is now a manager
            $this->assertDatabaseHas('project_roles', [
                'project_id' => $project->id,
                'user_id' => $creator->id,
                'role' => $managerRole
            ]);
            //assert manager is now a creator
            $this->assertDatabaseHas('project_roles', [
                'project_id' => $project->id,
                'user_id' => $manager->id,
                'role' => $creatorRole
            ]);
        }
    }
}
