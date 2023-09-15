<?php

namespace Routes\Api\external;


use Config;
use ec5\Mail\UserAccountDeletionConfirmation;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectArchive;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Users\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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

    public function testValidRequest()
    {
        //create mock user
        $mock = factory(User::class)->create();
        //get that user
        $user = User::where('email', $mock->email)->first();

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

    public function testInvalidRequest()
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

    public function testAccountDeletionPerformedWithoutRole()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();

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

    public function testAccountDeletionPerformedWithRoleCreator()
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
        $entries = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entries as $entry) {
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
        //assert project was archived (CREATOR role)
        $this->assertEquals(0, Project::where('id', $project->id)->count());
        $this->assertEquals(1, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries were not touched (they will be removed by a scheduled task)
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

    public function testAccountDeletionPerformedWithRoleManager()
    {
        //manager
        $role = Config::get('ec5Strings.project_roles.manager');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create();
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);

        //assign user to that project with the MANAGER role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is MANAGER
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));
        // 3 - add mock entries & branch entries to mock project
        $entries = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entries as $entry) {
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
        //assert project was NOT archived, as user has only MANAGER role
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        $this->assertEquals(0, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries were not touched
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

    public function testAccountDeletionPerformedWithRoleCurator()
    {
        //curator
        $role = Config::get('ec5Strings.project_roles.curator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create();
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
        $entries = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entries as $entry) {
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
        //assert project was NOT archived, as user has only CURATOR role
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        $this->assertEquals(0, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries were not touched
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

    public function testAccountDeletionPerformedWithRoleCollector()
    {
        //collector
        $role = Config::get('ec5Strings.project_roles.collector');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create();
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
        $entries = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entries as $entry) {
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
        //assert project was NOT archived, as user has only CURATOR role
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        $this->assertEquals(0, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries were not touched
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

    public function testAccountDeletionPerformedWithRoleViewer()
    {
        //viewer
        $role = Config::get('ec5Strings.project_roles.viewer');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create();
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
        $entries = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $project->id,
            'form_ref' => $project->ref . '_' . uniqid(),
            'user_id' => $project->created_by,
        ]);
        foreach ($entries as $entry) {
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
        //assert project was NOT archived, as user has only CURATOR role
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        $this->assertEquals(0, ProjectArchive::where('id', $project->id)->count());
        //assert entries & branch entries were not touched
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

}
