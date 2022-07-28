<?php

namespace Tests\Projects\Manage_users;

use Tests\TestCase;
use ec5\Http\Validation\Project\RuleSwitchUserRole;

use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Users\User;

use Config;
use Webpatser\Uuid\Uuid;

class RuleSwitchUserRoleTest extends TestCase
{
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

        //just in case we have a new db check these users exist
        $this->assertDatabaseHas('users', ['email' => env('CREATOR_EMAIL')]);
        $this->assertDatabaseHas('users', ['email' => env('MANAGER_EMAIL')]);

        $creatorRole = Config::get('ec5Permissions.projects.creator_role');
        $managerRole = Config::get('ec5Permissions.projects.manager_role');

        $slug = 'ec5-test-switch-role';
        $fakeEmail = 'jd@jd.com';

        $project = Project::firstOrNew(['slug' => $slug]);
        $project->name = 'EC5 Test Switch Role';
        $project->slug = $slug;
        $project->ref = str_replace('-', '', Uuid::generate(4));
        $project->description = 'This is a long description';
        $project->small_description = 'This is a small description';
        $project->logo_url = '';
        $project->access = 'private';
        $project->visibility = 'hidden';
        $project->category = 'general';
        $project->status = 'active';

        //get creator user
        $creator = User::where('email', env('CREATOR_EMAIL'))->first();

        //get manager
        $manager = User::where('email', env('MANAGER_EMAIL'))->first();

        //assign the project to the creator
        $project->created_by = $creator->id;

        try {
            $project->save();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        $projectCreatorRole = new ProjectRole();
        $projectCreatorRole->project_id = $project->id;
        $projectCreatorRole->role = $creatorRole;
        $projectCreatorRole->user_id = $creator->id;

        $projectManagerRole = new ProjectRole();
        $projectManagerRole->project_id = $project->id;
        $projectManagerRole->role = $managerRole;
        $projectManagerRole->user_id = $manager->id;

        $projectCreatorRole->save();
        $projectManagerRole->save();

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

        //create a new user
        $otherManager = new User();
        $otherManager->name = 'John';
        $otherManager->last_name = 'Doe';
        $otherManager->email = $fakeEmail;
        $otherManager->save();

        //assign the user to the project as manager
        $otherManagerRole = new ProjectRole();
        $otherManagerRole->project_id = $project->id;
        $otherManagerRole->role = $managerRole;
        $otherManagerRole->user_id = $otherManager->id;
        $otherManagerRole->save();

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
        $otherCurator = new User();
        $otherCurator->name = 'P';
        $otherCurator->last_name = 'M';
        $otherCurator->email = $fakeEmail . '1';
        $otherCurator->save();

        //manager can change curator/collector
        $this->validator->additionalChecks($manager, $otherCurator, 'manager', 'curator', 'collector');
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        $this->validator->additionalChecks($manager, $otherCurator, 'manager', 'collector', 'curator');
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();


        //delete test rows
        $project->delete();
        $projectCreatorRole->delete();
        $projectManagerRole->delete();
        $otherManagerRole->delete();
        $otherManager->delete();
        $otherCurator->delete();
    }
}
