<?php

namespace Tests\Routes\Api\internal;

use Config;
use ec5\Mail\UserAccountDeletionConfirmation;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\BranchEntryArchive;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\EntryArchive;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectArchive;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Users\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountDeletionInternalTest extends TestCase
{
    //to restore DB after tests
    use DatabaseTransactions;

    //internal routes use the default 'web; guard
    const DRIVER = 'web';

    public function setup()
    {
        parent::setUp();
    }

    /**
     * Test an authenticated user's routes
     */
    public function test_valid_request()
    {
        //create mock user
        $user = factory(User::class)->create();

        //account deletion request    
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        //assert user was dropped
        $this->assertEquals(0, User::where('email', $user->email)->count());
    }

    //no user, fail
    public function test_invalid_request()
    {
        $this->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(404)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_219",
                        "title" => "Page not found.",
                        "source" => "auth"
                    ]
                ]
            ]);
    }

    public function test_account_deletion_performed_without_role()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        //account deletion    
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        //assert user was dropped
        $this->assertEquals(0, User::where('email', $user->email)->count());
    }

    public function test_account_deletion_performed_with_role_creator()
    {
        //creator 
        $role = Config::get('ec5Strings.project_roles.creator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        // 2- create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is CREATOR
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));
        // 3 - add mock entries & branch entries to mock project
        $entriesToArchive = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entriesToArchive as $entry) {
            factory(BranchEntry::class, $numOfBranchEntries)->create([
                'project_id' => $project->id,
                'form_ref' => $project->ref . '_' . uniqid(),
                'user_id' => $project->created_by,
                'owner_entry_id' => $entry->id //FK!
            ]);
        }


        //4 delete user account
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was removed
        $this->assertEquals(0, User::where('email', $user->email)->count());
        //assert project was archived
        $this->assertEquals(0, Project::where('id', $project->id)->count());
        $this->assertEquals(1, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entriesare not touched
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $project->id)->count());
        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_account_deletion_performed_with_role_manager()
    {
        //MANAGER 
        $role = Config::get('ec5Strings.project_roles.manager');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->save();

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create(['state' => 'active']);
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);

        //assign user to that project with the MANAGER role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is CREATOR
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));
        // 3 - add mock entries & branch entries to mock project
        $entriesToArchive = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entriesToArchive as $entry) {
            factory(BranchEntry::class, $numOfBranchEntries)->create([
                'project_id' => $project->id,
                'form_ref' => $project->ref . '_' . uniqid(),
                'user_id' => $project->created_by,
                'owner_entry_id' => $entry->id //FK!
            ]);
        }

        //4 delete user account
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was removed
        $this->assertEquals(0, User::where('email', $user->email)->count());
        //assert project was NOT archived
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        $this->assertEquals(0, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries were NOT archived
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals(0, EntryArchive::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntry::where('project_id', $project->id)->count());
        $this->assertEquals(0, BranchEntryArchive::where('project_id', $project->id)->count());

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_account_deletion_performed_with_role_curator()
    {
        //CURATOR 
        $role = Config::get('ec5Strings.project_roles.curator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->save();

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create(['state' => 'active']);
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);

        //assign user to that project with the CURATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is CREATOR
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));
        // 3 - add mock entries & branch entries to mock project
        $entriesToArchive = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entriesToArchive as $entry) {
            factory(BranchEntry::class, $numOfBranchEntries)->create([
                'project_id' => $project->id,
                'form_ref' => $project->ref . '_' . uniqid(),
                'user_id' => $project->created_by,
                'owner_entry_id' => $entry->id //FK!
            ]);
        }


        //4 delete user account
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was removed
        $this->assertEquals(0, User::where('email', $user->email)->count());
        //assert project was NOT archived
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        $this->assertEquals(0, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries were NOT archived
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals(0, EntryArchive::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntry::where('project_id', $project->id)->count());
        $this->assertEquals(0, BranchEntryArchive::where('project_id', $project->id)->count());

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_account_deletion_performed_with_role_collector()
    {
        //COLLECTOR 
        $role = Config::get('ec5Strings.project_roles.collector');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->save();

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create(['state' => 'active']);
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);

        //assign user to that project with the COLLECTOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is CREATOR
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));
        // 3 - add mock entries & branch entries to mock project
        $entriesToArchive = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entriesToArchive as $entry) {
            factory(BranchEntry::class, $numOfBranchEntries)->create([
                'project_id' => $project->id,
                'form_ref' => $project->ref . '_' . uniqid(),
                'user_id' => $project->created_by,
                'owner_entry_id' => $entry->id //FK!
            ]);
        }


        //4 delete user account
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was removed
        $this->assertEquals(0, User::where('email', $user->email)->count());
        //assert project was NOT archived
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        $this->assertEquals(0, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries were NOT archived
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals(0, EntryArchive::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntry::where('project_id', $project->id)->count());
        $this->assertEquals(0, BranchEntryArchive::where('project_id', $project->id)->count());

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_account_deletion_performed_with_role_viewer()
    {
        //VIEWER
        $role = Config::get('ec5Strings.project_roles.viewer');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->save();

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create(['state' => 'active']);
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);

        //assign user to that project with the COLLECTOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is CREATOR
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));
        // 3 - add mock entries & branch entries to mock project
        $entriesToArchive = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entriesToArchive as $entry) {
            factory(BranchEntry::class, $numOfBranchEntries)->create([
                'project_id' => $project->id,
                'form_ref' => $project->ref . '_' . uniqid(),
                'user_id' => $project->created_by,
                'owner_entry_id' => $entry->id //FK!
            ]);
        }


        //4 delete user account
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was removed
        $this->assertEquals(0, User::where('email', $user->email)->count());
        //assert project was NOT archived
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        $this->assertEquals(0, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries were NOT archived
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals(0, EntryArchive::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntry::where('project_id', $project->id)->count());
        $this->assertEquals(0, BranchEntryArchive::where('project_id', $project->id)->count());

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

}