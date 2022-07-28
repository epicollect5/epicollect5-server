<?php

namespace Tests\TransferOwnershipTest;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\WithoutMiddleware;

use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Users\User;

use Webpatser\Uuid\Uuid;
use Exception;
use Mockery as m;
use Auth;
use Config;

class TransferOwnershipTest extends TestCase
{
    //use DatabaseMigrations;

    public function testTransferOwnership()
    {
        $slug = 'ec5-test-transfer-ownership';

        $creatorRole = Config::get('ec5Permissions.projects.creator_role');
        $managerRole = Config::get('ec5Permissions.projects.manager_role');

        $project = Project::firstOrNew(['slug' => $slug]);
        $project->name = 'EC5 Test Transfer Ownership';
        $project->slug = $slug;
        $project->ref = str_replace('-', '', Uuid::generate(4));
        $project->description = 'This is a long description';
        $project->small_description = 'This is a small description';
        $project->logo_url = '';
        $project->access = 'private';
        $project->visibility = 'hidden';
        $project->category = 'general';
        $project->status = 'active';

        $this->assertDatabaseHas('users', ['email' => env('CREATOR_EMAIL')]);
        $this->assertDatabaseHas('users', ['email' => env('MANAGER_EMAIL')]);

        //get creator user
        $creator = User::where('email', env('CREATOR_EMAIL'))->first();
        //get manager
        $manager = User::where('email', env('MANAGER_EMAIL'))->first();

        //assign the project to the creator
        $project->created_by = $creator->id;

        try {
            $project->save();
        } catch (Exception $e) {
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

        if ($project->transferOwnership($project->id, $projectCreatorRole->user_id, $projectManagerRole->user_id)) {

            //assert creator is now a manager
            $this->assertDatabaseHas($projectCreatorRole->getTable(), [
                'project_id' => $project->id,
                'user_id' => $projectCreatorRole->user_id,
                'role' => $managerRole
            ]);

            //assert manager is now a creator
            $this->assertDatabaseHas($projectCreatorRole->getTable(), [
                'project_id' => $project->id,
                'user_id' => $projectManagerRole->user_id,
                'role' => $creatorRole
            ]);
        } else {
            //transaction failed
            //todo
        }

        //delete test rows
        $project->delete();
        $projectCreatorRole->delete();
        $projectManagerRole->delete();
    }
}
