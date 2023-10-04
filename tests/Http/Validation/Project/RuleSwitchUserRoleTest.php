<?php

namespace Tests\Http\Validation\Project;

use Config;
use ec5\Http\Validation\Project\RuleSwitchUserRole;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Users\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RuleSwitchUserRoleTest extends TestCase
{
    use DatabaseTransactions;

    protected $validator;
    protected $inputs;

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->validator = new RuleSwitchUserRole();
    }

    public function testParameters()
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $inputs = [
            'currentRole' => 'curator',
            'newRole' => 'collector',
            'email' => $email
        ];

        // Valid params
        $this->validator->validate($inputs);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        $inputs = [
            'currentRole' => 'curator',
            'newRole' => 'curator',
            'email' => $email
        ];

        // Valid params
        $this->validator->validate($inputs);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        $inputs = [
            'currentRole' => 'cura-tor',
            'newRole' => 'curator',
            'email' => $email
        ];

        // Invalid currentRole
        $this->validator->validate($inputs);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        $inputs = [
            'currentRole' => 'curator',
            'newRole' => 'wow',
            'email' => env('SUPER_ADMIN_EMAIL')
        ];

        // Invalid newRole
        $this->validator->validate($inputs);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        $inputs = [
            'currentRole' => 'curator',
            'newRole' => 'collector',
            'email' => 'tony@tony'
        ];

        // Invalid email
        $this->validator->validate($inputs);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function testSwitchRoles()
    {
        $creatorRole = Config::get('ec5Permissions.projects.creator_role');
        $managerRole = Config::get('ec5Permissions.projects.manager_role');

        //create a fake user with creator email
        $creator = factory(User::class)->create([
            'email' => env('CREATOR_EMAIL')
        ]);

        //create a fake user with manager email
        $manager = factory(User::class)->create([
            'email' => env('MANAGER_EMAIL')
        ]);

        //create fake project with creator user
        $project = factory(Project::class)->create(
            ['created_by' => $creator->id]
        );

        //add creator role to that project
        $projectCreatorRole = factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);

        //add manager role to that project
        $projectManagerRole = factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => $managerRole
        ]);

        $this->assertDatabaseHas('users', ['email' => env('CREATOR_EMAIL')]);
        $this->assertDatabaseHas('users', ['email' => env('MANAGER_EMAIL')]);

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

        //creator switch manager to curator
        if ($projectCreatorRole->switchUserRole($project->id, $manager, 'manager', 'curator')) {
            //assert manager is now a curator
            $this->assertDatabaseHas($projectCreatorRole->getTable(), [
                'project_id' => $project->id,
                'user_id' => $projectManagerRole->user_id,
                'role' => 'curator'
            ]);
        }

        //creator switch curator to collector
        if ($projectCreatorRole->switchUserRole($project->id, $manager, 'curator', 'collector')) {
            //assert manager is now a curator
            $this->assertDatabaseHas($projectCreatorRole->getTable(), [
                'project_id' => $project->id,
                'user_id' => $projectManagerRole->user_id,
                'role' => 'collector'
            ]);
        }

        //creator switch collector to manager
        if ($projectCreatorRole->switchUserRole($project->id, $manager, 'collector', 'manager')) {
            //assert manager is now a curator
            $this->assertDatabaseHas($projectCreatorRole->getTable(), [
                'project_id' => $project->id,
                'user_id' => $projectManagerRole->user_id,
                'role' => 'manager'
            ]);
        }

        //user cannot change its own role
        $this->validator->additionalChecks($creator, $creator, 'creator', 'manager', 'creator');
        $this->assertTrue($this->validator->hasErrors());
        $this->assertArrayHasKey('user', $this->validator->errors);
        $this->assertContains('ec5_217', $this->validator->errors['user']);
        $this->validator->resetErrors();


        $otherManager = factory(User::class)->create();
        $otherManagerRole = factory(ProjectRole::class)->create(
            [
                'user_id' => $otherManager->id,
                'project_id' => $project->id,
                'role' => $managerRole
            ]);


        //manager cannot change manager
        $this->validator->additionalChecks($manager, $otherManager, 'manager', 'curator', 'manager');
        $this->assertTrue($this->validator->hasErrors());
        $this->assertArrayHasKey('user', $this->validator->errors);
        $this->assertContains('ec5_91', $this->validator->errors['user']);
        $this->validator->resetErrors();

        //manager cannot change creator
        $this->validator->additionalChecks($manager, $creator, 'manager', 'creator', 'manager');
        $this->assertTrue($this->validator->hasErrors());
        $this->assertArrayHasKey('user', $this->validator->errors);
        $this->assertContains('ec5_91', $this->validator->errors['user']);
        $this->validator->resetErrors();

        //create a new user
        $otherCurator = factory(User::class)->create();
        $otherCuratorRole = factory(ProjectRole::class)->create(
            [
                'user_id' => $otherCurator->id,
                'project_id' => $project->id,
                'role' => 'curator'
            ]);

        //manager can change curator/collector
        $this->validator->additionalChecks($manager, $otherCurator, 'manager', 'curator', 'collector');
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        $this->validator->additionalChecks($manager, $otherCurator, 'manager', 'collector', 'curator');
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }
}
