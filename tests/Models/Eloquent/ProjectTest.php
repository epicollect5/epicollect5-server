<?php

namespace Tests\Models\Eloquent;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        $this->clearDatabase([]);
    }

    public function test_transfer_ownership()
    {
        $creatorRole = config('epicollect.permissions.projects.creator_role');
        $managerRole = config('epicollect.permissions.projects.manager_role');

        //create a fake user with creator email
        $creator = factory(User::class)->create([
            'email' => config('testing.CREATOR_EMAIL')
        ]);

        //create a fake user with manager email
        $manager = factory(User::class)->create([
            'email' => config('testing.MANAGER_EMAIL')
        ]);

        //create fake project with creator user
        $project = factory(Project::class)->create(
            ['created_by' => $creator->id]
        );

        //add creator role to that project
        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);

        //add manager role to that project
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => $managerRole
        ]);

        $this->assertDatabaseHas('users', ['email' => config('testing.CREATOR_EMAIL')]);
        $this->assertDatabaseHas('users', ['email' => config('testing.MANAGER_EMAIL')]);

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

    public function test_creator_email()
    {
        $creatorRole = config('epicollect.permissions.projects.creator_role');
        //create a fake user with creator email
        $creator = factory(User::class)->create([
            'email' => config('testing.CREATOR_EMAIL')
        ]);
        //create the fake project with creator user
        $project = factory(Project::class)->create(
            ['created_by' => $creator->id]
        );
        //add the creator role to that project
        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);
        $this->assertDatabaseHas('users', ['email' => config('testing.CREATOR_EMAIL')]);
        $this->assertDatabaseHas('project_roles', [
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);

        $email = Project::creatorEmail($project->id);
        $this->assertEquals(config('testing.CREATOR_EMAIL'), $email);

        //remove creator and retest (safety net)
        User::where('email', $email)->delete();
        $email = Project::creatorEmail($project->id);
        $this->assertEquals('n/a', $email);
    }
}
