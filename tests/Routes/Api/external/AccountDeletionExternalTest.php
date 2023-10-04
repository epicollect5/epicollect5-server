<?php

namespace Tests\Routes\Api\external;


use ec5\Mail\UserAccountDeletionUser;
use ec5\Mail\UserAccountDeletionAdmin;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\BranchEntryArchive;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\EntryArchive;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectArchive;
use ec5\Models\Users\User;
use ec5\Models\Eloquent\ProjectRole;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use ec5\Mail\UserAccountDeletionConfirmation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Config;
use Tests\TestCase;

class AccountDeletionExternalTest extends TestCase
{
    /**
     * Test an authenticated user's routes
     * imp: avoid $this->actingAs($user, 'api_external');
     * imp: as that create a valid user object therefore bypassing
     * imp: jwt validation. We need to send a valid token per each request
     * imp: instead.
     */

    use DatabaseTransactions;


    public function setup()
    {
        parent::setUp();
    }

    public function test_valid_request_role_none()
    {
        //create fake user
        factory(User::class)->create(
            ['email' => env('UNIT_TEST_RANDOM_EMAIL')]
        );

        $user = User::where('email', env('UNIT_TEST_RANDOM_EMAIL'))->first();
        $user->state = 'active';

        //user must have no roles in any project
        $projectRoles = ProjectRole::where('user_id', $user->id)->first();
        $this->assertEmpty($projectRoles);

        //Login user as passwordless to get a JWT
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT
        Mail::fake();
        $this->json('POST', '/api/profile/account-deletion-request', [], [
            'Authorization' => 'Bearer ' . $jwt
        ])
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
    }

    public function test_valid_request_role_creator()
    {
        $role = Config::get('ec5Strings.project_roles.creator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create fake user
        $user = factory(User::class)->create(
            ['email' => env('UNIT_TEST_RANDOM_EMAIL')]
        );

        //create fake project
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign project to user as creator
        factory(ProjectRole::class)->create(
            [
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => $role
            ]
        );
        //user must have creator role in at least one project
        $projectRoles = ProjectRole::where('user_id', $user->id)->first();
        $this->assertEquals(1, sizeOf($projectRoles));
        $this->assertEquals($role, $projectRoles['role']);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->where('created_by', $user->id)->count());
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

        //Login user as passwordless to get a JWT
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT
        Mail::fake();
        $this->json('POST', '/api/profile/account-deletion-request', [], [
            'Authorization' => 'Bearer ' . $jwt
        ])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was removed
        $this->assertEquals(0, User::where('email', $user->email)->count());
        //assert project WAS archived
        $this->assertEquals(0, Project::where('id', $project->id)->count());
        $this->assertEquals(1, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries are NOT touched
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

    public function test_valid_request_role_manager()
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

        //Login user as passwordless to get a JWT
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT
        Mail::fake();
        $this->json('POST', '/api/profile/account-deletion-request', [], [
            'Authorization' => 'Bearer ' . $jwt
        ])
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

    public function test_valid_request_role_curator()
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
        //assert user role  is CURATOR
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

        //Login user as passwordless to get a JWT
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT
        Mail::fake();
        $this->json('POST', '/api/profile/account-deletion-request', [], [
            'Authorization' => 'Bearer ' . $jwt
        ])
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

    public function test_valid_request_role_collector()
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
        //assert user role  is CURATOR
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

        //Login user as passwordless to get a JWT
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT
        Mail::fake();
        $this->json('POST', '/api/profile/account-deletion-request', [], [
            'Authorization' => 'Bearer ' . $jwt
        ])
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

    public function test_valid_request_role_viewer()
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

        //assign user to that project with the VIEWER role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is CURATOR
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

        //Login user as passwordless to get a JWT
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT
        Mail::fake();
        $this->json('POST', '/api/profile/account-deletion-request', [], [
            'Authorization' => 'Bearer ' . $jwt
        ])
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

    public function test_invalid_request()
    {
        //account deletion request without JWT
        $response = $this->json('POST', '/api/profile/account-deletion-request', [], []);
        $response->assertStatus(404)
            ->assertExactJson([
                'errors' => [
                    [
                        "code" => "ec5_219",
                        "title" => "Page not found.",
                        "source" => "auth"
                    ]
                ],
            ]);
    }

    public function test_account_deletion()
    {
        //create a fake user ans save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->email = 'user-to-be-deleted@example.com';
        $user->save();

        //Login manager user as passwordless to get a JWT
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT
        Mail::fake();
        $this->json('POST', '/api/profile/account-deletion-request', [], [
            'Authorization' => 'Bearer ' . $jwt
        ])
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
    }
}
