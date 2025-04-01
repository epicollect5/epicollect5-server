<?php

namespace Tests\Http\Controllers\Api\AccountController;

use ec5\Libraries\Utilities\Generators;
use ec5\Mail\UserAccountDeletionConfirmation;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\OAuth\OAuthClientProject;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountDeletionInternalTest extends TestCase
{
    //to restore DB after tests
    use DatabaseTransactions;

    //internal routes use the default 'web' guard
    public const string DRIVER = 'web';

    public function setup(): void
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
        $this->actingAs($user, self::DRIVER)
            ->json('POST', Route('internalAccountDelete'), [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //assert user was dropped
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(0, User::where('id', $user->id)->count());
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
        $this->actingAs($user, self::DRIVER)
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
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //assert user was dropped
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(0, User::where('id', $user->id)->count());

        //assert provider is removed
        $this->assertEquals(0, UserProvider::where('email', $user->email)
            ->count());
        $this->assertEquals(0, UserProvider::where('user_id', $user->id)
            ->count());
    }

    public function test_account_deletion_performed_with_role_creator_and_entries()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();
        $projectStatsCount = ProjectStats::count();

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        //add a user provider
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        // 2- create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assign a few random project members with other roles (not creator)
        $numOfMembers = mt_rand(2, 100);
        $roles = [
            config('epicollect.strings.project_roles.manager'),
            config('epicollect.strings.project_roles.curator'),
            config('epicollect.strings.project_roles.collector'),
            config('epicollect.strings.project_roles.viewer'),
        ];
        for ($i = 0; $i < $numOfMembers; $i++) {
            $member = factory(User::class)->create();
            factory(ProjectRole::class)->create([
                'user_id' => $member->id,
                'project_id' => $project->id,
                'role' => $roles[array_rand($roles)]
            ]);
        }

        //add fake stats
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => $numOfEntries
        ]);

        //assert the project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert the user role is CREATOR
        $this->assertEquals(1 + $numOfMembers, ProjectRole::where('project_id', $project->id)->count());
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
            //add a fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        }

        //assert files exist
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);

        //4 delete user account
        Mail::fake();
        $this->actingAs($user, self::DRIVER)
            ->json('POST', Route('internalAccountDelete'), [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was archived and email scrambled (due to DB uniqueness constraint)
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(1, User::where('id', $user->id)->count());

        //assert provider is removed
        $this->assertEquals(0, UserProvider::where('email', $user->email)
            ->count());
        $this->assertEquals(0, UserProvider::where('user_id', $user->id)
            ->count());

        //assert the project was archived
        $this->assertEquals(1, Project::where('id', $project->id)
            ->where('status', 'archived')
            ->count());

        //assert roles were dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());

        //assert entries & branch entries are NOT touched
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $project->id)->count());

        //assert media are NOT touched
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($project->ref);
        Storage::disk('audio')->deleteDirectory($project->ref);
        Storage::disk('video')->deleteDirectory($project->ref);

        //check counts
        $this->assertEquals($projectsCount + 1, Project::count());
        $this->assertEquals($entriesCount + $numOfEntries, Entry::count());
        $this->assertEquals($branchEntriesCount + ($numOfEntries * $numOfBranchEntries), BranchEntry::count());
        $this->assertEquals($projectStatsCount + 1, ProjectStats::count());

    }

    public function test_account_deletion_performed_with_role_creator_but_no_entries()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();
        $projectStatsCount = ProjectStats::count();

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);
        // 2- create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //add fake stats
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0
        ]);
        //fake structure
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        //fake app
        factory(OAuthClientProject::class)->create(
            ['project_id' => $project->id]
        );

        //assert the project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert the user role is CREATOR
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));

        //4 delete user account
        Mail::fake();
        $this->actingAs($user, self::DRIVER)
            ->json('POST', Route('internalAccountDelete'), [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was archived, not removed
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(1, User::where('id', $user->id)->count());

        //assert provider is removed
        $this->assertEquals(0, UserProvider::where('email', $user->email)
            ->count());
        $this->assertEquals(0, UserProvider::where('user_id', $user->id)
            ->count());

        //assert the project was removed
        $this->assertEquals(0, Project::where('id', $project->id)
            ->count());

        //assert stats are dropped
        $this->assertEquals(0, ProjectStats::where('project_id', $project->id)
            ->count());
        //assert structure is dropped
        $this->assertEquals(0, ProjectStructure::where('project_id', $project->id)
            ->count());
        //assert app clients are dropped
        $this->assertEquals(0, OAuthClientProject::where('project_id', $project->id)
            ->count());

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($project->ref);
        Storage::disk('audio')->deleteDirectory($project->ref);
        Storage::disk('video')->deleteDirectory($project->ref);

        //check counts
        $this->assertEquals($projectsCount, Project::count());
        $this->assertEquals($entriesCount, Entry::count());
        $this->assertEquals($branchEntriesCount, BranchEntry::count());
        $this->assertEquals($projectStatsCount, ProjectStats::count());
    }

    public function test_account_deletion_performed_with_role_manager()
    {
        //MANAGER
        $role = config('epicollect.strings.project_roles.manager');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();
        $projectStatsCount = ProjectStats::count();

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->save();

        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create(['state' => 'active']);
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);

        //assign user to that project with the MANAGER role
        factory(ProjectRole::class)->create([
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

            //add a fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        }

        //assert files exist
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);

        //4 delete user account
        Mail::fake();
        $this->actingAs($user, self::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was archived and email scrambled (due to DB uniqueness constraint)
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(1, User::where('id', $user->id)->count());

        //assert provider is removed
        $this->assertEquals(0, UserProvider::where('email', $user->email)
            ->count());
        $this->assertEquals(0, UserProvider::where('user_id', $user->id)
            ->count());

        //assert project was NOT archived
        $this->assertEquals(0, Project::where('id', $project->id)
            ->where('status', 'archived')
            ->count());
        //assert entries & branch entries were NOT archived
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntry::where('project_id', $project->id)->count());

        //assert media are NOT touched
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($project->ref);
        Storage::disk('audio')->deleteDirectory($project->ref);
        Storage::disk('video')->deleteDirectory($project->ref);

        $this->assertEquals($projectsCount + 1, Project::count());
        $this->assertEquals($entriesCount + $numOfEntries, Entry::count());
        $this->assertEquals($branchEntriesCount + ($numOfEntries * $numOfBranchEntries), BranchEntry::count());
        $this->assertEquals($projectStatsCount, ProjectStats::count());
    }

    public function test_account_deletion_performed_with_role_curator()
    {
        //CURATOR
        $role = config('epicollect.strings.project_roles.curator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();
        $projectStatsCount = ProjectStats::count();

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->save();

        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create(['state' => 'active']);
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);

        //assign user to that project with the CURATOR role
        factory(ProjectRole::class)->create([
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

            //add a fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        }

        //assert files exist
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);


        //4 delete user account
        Mail::fake();
        $this->actingAs($user, self::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was archived and email scrambled (due to DB uniqueness constraint)
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(1, User::where('id', $user->id)->count());

        //assert provider is removed
        $this->assertEquals(0, UserProvider::where('email', $user->email)
            ->count());
        $this->assertEquals(0, UserProvider::where('user_id', $user->id)
            ->count());

        //assert project was NOT archived
        $this->assertEquals(0, Project::where('id', $project->id)
            ->where('status', 'archived')
            ->count());
        //assert entries & branch entries were NOT archived
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntry::where('project_id', $project->id)->count());

        //assert media files are not touched
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($project->ref);
        Storage::disk('audio')->deleteDirectory($project->ref);
        Storage::disk('video')->deleteDirectory($project->ref);

        $this->assertEquals($projectsCount + 1, Project::count());
        $this->assertEquals($entriesCount + $numOfEntries, Entry::count());
        $this->assertEquals($branchEntriesCount + ($numOfEntries * $numOfBranchEntries), BranchEntry::count());
        $this->assertEquals($projectStatsCount, ProjectStats::count());
    }

    public function test_account_deletion_performed_with_role_collector()
    {
        //COLLECTOR
        $role = config('epicollect.strings.project_roles.collector');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();
        $projectStatsCount = ProjectStats::count();


        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->save();

        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create(['state' => 'active']);
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);

        //assign user to that project with the COLLECTOR role
        factory(ProjectRole::class)->create([
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

            //add a fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        }

        //assert files exist
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);

        //4 delete user account
        Mail::fake();
        $this->actingAs($user, self::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was archived and email scrambled (due to DB uniqueness constraint)
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(1, User::where('id', $user->id)->count());

        //assert provider is removed
        $this->assertEquals(0, UserProvider::where('email', $user->email)
            ->count());
        $this->assertEquals(0, UserProvider::where('user_id', $user->id)
            ->count());

        //assert project was NOT archived
        $this->assertEquals(0, Project::where('id', $project->id)
            ->where('status', 'archived')
            ->count());
        //assert entries & branch entries were NOT archived
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntry::where('project_id', $project->id)->count());

        //assert media files are not touched
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($project->ref);
        Storage::disk('audio')->deleteDirectory($project->ref);
        Storage::disk('video')->deleteDirectory($project->ref);

        //check counts
        $this->assertEquals($projectsCount + 1, Project::count());
        $this->assertEquals($entriesCount + $numOfEntries, Entry::count());
        $this->assertEquals($branchEntriesCount + ($numOfEntries * $numOfBranchEntries), BranchEntry::count());
        $this->assertEquals($projectStatsCount, ProjectStats::count());
    }

    public function test_account_deletion_performed_with_role_viewer()
    {
        //VIEWER
        $role = config('epicollect.strings.project_roles.viewer');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();
        $projectStatsCount = ProjectStats::count();

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->save();

        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        // 2- create mock project with another user set as CREATOR
        $anotherUser = factory(User::class)->create(['state' => 'active']);
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);

        //assign user to that project with the COLLECTOR role
        factory(ProjectRole::class)->create([
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

            //add a fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        }

        //assert files exist
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);


        //4 delete user account
        Mail::fake();
        $this->actingAs($user, self::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was archived
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(1, User::where('id', $user->id)->count());

        //assert provider is removed
        $this->assertEquals(0, UserProvider::where('email', $user->email)
            ->count());
        $this->assertEquals(0, UserProvider::where('user_id', $user->id)
            ->count());

        //assert project was NOT archived
        $this->assertEquals(0, Project::where('id', $project->id)
            ->where('status', 'archived')
            ->count());
        //assert entries & branch entries were NOT archived
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntry::where('project_id', $project->id)->count());

        //assert media files are not touched
        $photos = Storage::disk('entry_original')->files($project->ref);
        $this->assertGreaterThan(0, count($photos));
        $this->assertCount($numOfEntries, $photos);

        $audios = Storage::disk('audio')->files($project->ref);
        $this->assertGreaterThan(0, count($audios));
        $this->assertCount($numOfEntries, $audios);

        $videos = Storage::disk('video')->files($project->ref);
        $this->assertGreaterThan(0, count($videos));
        $this->assertCount($numOfEntries, $videos);

        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(0, ProjectRole::where('user_id', $user->id)->count());

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //delete fake files
        Storage::disk('entry_original')->deleteDirectory($project->ref);
        Storage::disk('audio')->deleteDirectory($project->ref);
        Storage::disk('video')->deleteDirectory($project->ref);

        //check counts
        $this->assertEquals($projectsCount + 1, Project::count());
        $this->assertEquals($entriesCount + $numOfEntries, Entry::count());
        $this->assertEquals($branchEntriesCount + ($numOfEntries * $numOfBranchEntries), BranchEntry::count());
        $this->assertEquals($projectStatsCount, ProjectStats::count());

    }

    public function test_account_deletion_performed_with_mixed_roles_and_entries()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $otherRoles = [
            config('epicollect.strings.project_roles.manager'),
            config('epicollect.strings.project_roles.curator'),
            config('epicollect.strings.project_roles.collector'),
            config('epicollect.strings.project_roles.viewer')
        ];
        $projectsWithOtherRoles = [];
        $projectRefs = [];
        $numOfEntries = mt_rand(1, 10);
        $numOfBranchEntries = mt_rand(1, 10);

        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();
        $projectStatsCount = ProjectStats::count();

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);
        //create another user
        $anotherUser = factory(User::class)->create();
        factory(UserProvider::class)->create([
            'user_id' => $anotherUser->id,
            'email' => $anotherUser->email
        ]);

        // 2- create a couple of projects with that user
        $projectRoleCreatorOne = factory(Project::class)->create(['created_by' => $user->id]);
        $projectRefs[] = $projectRoleCreatorOne->ref;
        $projectRoleCreatorTwo = factory(Project::class)->create(['created_by' => $user->id]);
        $projectRefs[] = $projectRoleCreatorTwo->ref;
        //assign the user to those projects with the CREATOR role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $projectRoleCreatorOne->id,
            'role' => $role
        ]);

        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $projectRoleCreatorTwo->id,
            'role' => $role
        ]);

        //assign other users with lower roles to those projects
        $numOfMembers = mt_rand(2, 10);
        $roles = [
            config('epicollect.strings.project_roles.manager'),
            config('epicollect.strings.project_roles.curator'),
            config('epicollect.strings.project_roles.collector'),
            config('epicollect.strings.project_roles.viewer'),
        ];
        for ($i = 0; $i < $numOfMembers; $i++) {
            $member = factory(User::class)->create();
            factory(ProjectRole::class)->create([
                'user_id' => $member->id,
                'project_id' => $projectRoleCreatorOne->id,
                'role' => $roles[array_rand($roles)]
            ]);
            factory(ProjectRole::class)->create([
                'user_id' => $member->id,
                'project_id' => $projectRoleCreatorTwo->id,
                'role' => $roles[array_rand($roles)]
            ]);
        }
        //add fake stats
        factory(ProjectStats::class)->create([
            'project_id' => $projectRoleCreatorOne->id,
            'total_entries' => $numOfEntries
        ]);
        factory(ProjectStats::class)->create([
            'project_id' => $projectRoleCreatorTwo->id,
            'total_entries' => $numOfEntries
        ]);

        //create a fake project per each role and assign it to the user
        foreach ($otherRoles as $otherRole) {
            $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
            //add fake stats
            factory(ProjectStats::class)->create([
                'project_id' => $project->id,
                'total_entries' => 0
            ]);
            $projectRefs[] = $project->ref;
            $projectsWithOtherRoles[] = [
                'id' => $project->id,
                'role' => $otherRole
            ];

            factory(ProjectRole::class)->create([
                'user_id' => $anotherUser->id,
                'project_id' => $project->id,
                'role' => $role
            ]);
            factory(ProjectRole::class)->create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => $otherRole
            ]);

            //assert the project is present
            $this->assertEquals(1, Project::where('id', $project->id)->count());
            //assert user roles for that project
            $this->assertEquals(2, ProjectRole::where('project_id', $project->id)->count());
            $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $anotherUser->id)->value('role'));
            $this->assertEquals($otherRole, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));


            //for each project, add some fake entries and branch entries
            //firstly, entries by creator
            $entriesByCreator = factory(Entry::class, $numOfEntries)->create([
                'project_id' => $project->id,
                'form_ref' => $project->ref . '_' . uniqid(),
                'user_id' => $anotherUser->id,
            ]);

            //branch entries by creator
            foreach ($entriesByCreator as $entry) {
                factory(BranchEntry::class, $numOfBranchEntries)->create([
                    'project_id' => $project->id,
                    'form_ref' => Generators::formRef($project->ref),
                    'user_id' => $anotherUser->id,
                    'owner_entry_id' => $entry->id //FK!
                ]);

                //add a fake files per each entry (per each media type)
                //photo
                Storage::disk('entry_original')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
                //audio
                Storage::disk('audio')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
                //video
                Storage::disk('video')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            }
            //secondly, entries by the other roles (aside from viewer)
            if ($otherRole !== 'viewer') {
                $entriesByOtherRole = factory(Entry::class, $numOfEntries)->create([
                    'project_id' => $project->id,
                    'form_ref' => Generators::formRef($project->ref),
                    'user_id' => $user->id
                ]);

                foreach ($entriesByOtherRole as $entry) {
                    factory(BranchEntry::class, $numOfBranchEntries)->create([
                        'project_id' => $project->id,
                        'form_ref' => Generators::formRef($project->ref),
                        'user_id' => $user->id,
                        'owner_entry_id' => $entry->id //FK!
                    ]);

                    //add a fake file per each entry (per each media type)
                    //photo
                    Storage::disk('entry_original')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
                    //audio
                    Storage::disk('audio')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
                    //video
                    Storage::disk('video')->put($project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
                }
                //assert entries exist
                $this->assertEquals(2 * $numOfEntries, Entry::where('project_id', $project->id)
                    ->count());

                //assert branch entries exist
                $this->assertEquals(2 * ($numOfEntries * $numOfBranchEntries), BranchEntry::where('project_id', $project->id)
                    ->count());

                //assert files exist (2x, as by creator and by the other roles)
                $photos = Storage::disk('entry_original')->files($project->ref);
                $this->assertGreaterThan(0, count($photos));
                $this->assertCount(2 * $numOfEntries, $photos);

                $audios = Storage::disk('audio')->files($project->ref);
                $this->assertGreaterThan(0, count($audios));
                $this->assertCount(2 * $numOfEntries, $audios);

                $videos = Storage::disk('video')->files($project->ref);
                $this->assertGreaterThan(0, count($videos));
                $this->assertCount(2 * $numOfEntries, $videos);

                // Assert entries by creator
                $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)
                    ->where('user_id', $user->id)
                    ->count());
                //assert entries by other roles
                $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)
                    ->where('user_id', $anotherUser->id)
                    ->count());
            } else {
                //assert entries exist
                $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)
                    ->count());
                //assert entries by creator
                $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)
                    ->where('user_id', $anotherUser->id)
                    ->count());

                //assert files exist (1x,  by creator since viewer cannot add entries)
                $photos = Storage::disk('entry_original')->files($project->ref);
                $this->assertGreaterThan(0, count($photos));
                $this->assertCount($numOfEntries, $photos);

                $audios = Storage::disk('audio')->files($project->ref);
                $this->assertGreaterThan(0, count($audios));
                $this->assertCount($numOfEntries, $audios);

                $videos = Storage::disk('video')->files($project->ref);
                $this->assertGreaterThan(0, count($videos));
                $this->assertCount($numOfEntries, $videos);
            }
        }

        //assert projects are present
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert all roles are present
        $this->assertEquals(1 + $numOfMembers, ProjectRole::where('project_id', $projectRoleCreatorOne->id)->count());
        $this->assertEquals(1 + $numOfMembers, ProjectRole::where('project_id', $projectRoleCreatorTwo->id)->count());
        //user should be a member of 6 projects, 2 with role creator and 4 with the other roles
        $this->assertEquals(6, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $projectRoleCreatorOne->id)->where('user_id', $user->id)->value('role'));
        $this->assertEquals($role, ProjectRole::where('project_id', $projectRoleCreatorTwo->id)->where('user_id', $user->id)->value('role'));
        // 3 - add mock entries & branch entries to mock projects
        $entriesToArchiveOne = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $projectRoleCreatorOne->id,
            'form_ref' => $projectRoleCreatorOne->ref . '_' . uniqid(),
            'user_id' => $projectRoleCreatorOne->created_by,
        ]);
        foreach ($entriesToArchiveOne as $entry) {
            factory(BranchEntry::class, $numOfBranchEntries)->create([
                'project_id' => $projectRoleCreatorOne->id,
                'form_ref' => $projectRoleCreatorOne->ref . '_' . uniqid(),
                'user_id' => $projectRoleCreatorOne->created_by,
                'owner_entry_id' => $entry->id //FK!
            ]);
            //add s fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($projectRoleCreatorOne->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($projectRoleCreatorOne->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($projectRoleCreatorOne->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        }

        $entriesToArchiveTwo = factory(Entry::class, $numOfEntries)->create([
            'project_id' => $projectRoleCreatorTwo->id,
            'form_ref' => $projectRoleCreatorTwo->ref . '_' . uniqid(),
            'user_id' => $projectRoleCreatorTwo->created_by,
        ]);
        foreach ($entriesToArchiveTwo as $entry) {
            factory(BranchEntry::class, $numOfBranchEntries)->create([
                'project_id' => $projectRoleCreatorTwo->id,
                'form_ref' => $projectRoleCreatorTwo->ref . '_' . uniqid(),
                'user_id' => $projectRoleCreatorTwo->created_by,
                'owner_entry_id' => $entry->id //FK!
            ]);
            //add a fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($projectRoleCreatorTwo->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($projectRoleCreatorTwo->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($projectRoleCreatorTwo->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        }

        //assert entries are present
        $this->assertEquals($numOfEntries, Entry::where('project_id', $projectRoleCreatorOne->id)
            ->where('user_id', $user->id)
            ->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $projectRoleCreatorOne->id)
            ->where('user_id', $user->id)
            ->count());
        $this->assertEquals($numOfEntries, Entry::where('project_id', $projectRoleCreatorTwo->id)
            ->where('user_id', $user->id)
            ->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $projectRoleCreatorTwo->id)
            ->where('user_id', $user->id)
            ->count());


        //4 delete user account
        Mail::fake();
        $this->actingAs($user, self::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was archived and email scrambled (due to DB uniqueness constraint)
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(1, User::where('id', $user->id)->count());

        //assert provider is removed
        $this->assertEquals(0, UserProvider::where('email', $user->email)
            ->count());
        $this->assertEquals(0, UserProvider::where('user_id', $user->id)
            ->count());

        //assert projects with CREATOR role were archived
        $this->assertEquals(1, Project::where('id', $projectRoleCreatorOne->id)
            ->where('status', 'archived')
            ->count());
        $this->assertEquals(1, Project::where('id', $projectRoleCreatorTwo->id)
            ->where('status', 'archived')
            ->count());

        //assert all roles were dropped for those projects
        $this->assertEquals(
            0,
            ProjectRole::where('project_id', $projectRoleCreatorOne->id)->count()
        );

        $this->assertEquals(
            0,
            ProjectRole::where('project_id', $projectRoleCreatorTwo->id)->count()
        );

        //assert entries by CREATOR are not touched; we just archive the projects created by the user when its account is archived
        $this->assertEquals($numOfEntries, Entry::where('project_id', $projectRoleCreatorOne->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $projectRoleCreatorOne->id)->count());
        $this->assertEquals($numOfEntries, Entry::where('project_id', $projectRoleCreatorTwo->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $projectRoleCreatorTwo->id)->count());

        foreach ($projectsWithOtherRoles as $projectWithOtherRole) {
            //assert projects with other roles are NOT archived
            $projectId = $projectWithOtherRole['id'];
            $otherRole = $projectWithOtherRole['role'];
            $this->assertEquals(0, Project::where('id', $projectId)
                ->where('status', 'archived')
                ->count());

            //assert entries by other roles are not touched; we just anonymize entries
            if ($otherRole !== 'viewer') {
                $this->assertEquals(2 * $numOfEntries, Entry::where('project_id', $projectId)->count());
                $this->assertEquals(2 * ($numOfBranchEntries * $numOfEntries), BranchEntry::where('project_id', $projectId)->count());
            } else {
                $this->assertEquals($numOfEntries, Entry::where('project_id', $projectId)->count());
                $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $projectId)->count());
            }

            //assert all other roles are dropped, but not creator
            $this->assertEquals(0, ProjectRole::where('project_id', $projectId)
                ->where('role', '!=', $role)
                ->count());
            $this->assertEquals(1, ProjectRole::where('project_id', $projectId)
                ->where('role', $role)
                ->count());
        }

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //assert files are not touched
        //imp: first 2 projects and last one have half the entries as they only have entries for the creator role
        foreach ($projectRefs as $index => $projectRef) {

            $multiplier = ($index > 1 && $index < count($projectRefs) - 1) ? 2 : 1;

            $photos = Storage::disk('entry_original')->files($projectRef);
            $this->assertGreaterThan(0, count($photos));
            $this->assertCount($multiplier * $numOfEntries, $photos, 'ref ->' . $projectRef . '  index ' . $index);

            $audios = Storage::disk('audio')->files($projectRef);
            $this->assertGreaterThan(0, count($audios));
            $this->assertCount($multiplier * $numOfEntries, $audios);

            $videos = Storage::disk('video')->files($projectRef);
            $this->assertGreaterThan(0, count($videos));
            $this->assertCount($multiplier * $numOfEntries, $videos);

        }

        //delete fake files for all the projects
        foreach ($projectRefs as $projectRef) {
            Storage::disk('entry_original')->deleteDirectory($projectRef);
            Storage::disk('audio')->deleteDirectory($projectRef);
            Storage::disk('video')->deleteDirectory($projectRef);
        }

        //check counts
        $this->assertEquals($projectsCount + 6, Project::count());
        $this->assertEquals($entriesCount + (6 * $numOfEntries) + (3 * $numOfEntries), Entry::count());
        $this->assertEquals($branchEntriesCount + (6 * $numOfEntries * $numOfBranchEntries) + (3 * $numOfEntries * $numOfBranchEntries), BranchEntry::count());
        $this->assertEquals($projectStatsCount + 6, ProjectStats::count());

    }

    public function test_account_deletion_performed_with_mixed_roles_but_no_entries()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $otherRoles = [
            config('epicollect.strings.project_roles.manager'),
            config('epicollect.strings.project_roles.curator'),
            config('epicollect.strings.project_roles.collector'),
            config('epicollect.strings.project_roles.viewer')
        ];
        $projectsWithOtherRoles = [];

        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();
        $projectStatsCount = ProjectStats::count();

        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        //add a user provider
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email
        ]);
        //create another user
        $anotherUser = factory(User::class)->create();
        //add a user provider
        factory(UserProvider::class)->create([
            'user_id' => $anotherUser->id,
            'email' => $anotherUser->email
        ]);

        // 2- create a couple of projects with that user
        $projectRoleCreatorOne = factory(Project::class)->create(['created_by' => $user->id]);
        $projectRoleCreatorTwo = factory(Project::class)->create(['created_by' => $user->id]);
        //assign the user to those projects with the CREATOR role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $projectRoleCreatorOne->id,
            'role' => $role
        ]);

        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $projectRoleCreatorTwo->id,
            'role' => $role
        ]);

        //add fake stats
        factory(ProjectStats::class)->create([
            'project_id' => $projectRoleCreatorOne->id,
            'total_entries' => 0
        ]);
        factory(ProjectStats::class)->create([
            'project_id' => $projectRoleCreatorTwo->id,
            'total_entries' => 0
        ]);

        //create a fake project per each role and assign it to the user
        foreach ($otherRoles as $otherRole) {
            $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
            $projectsWithOtherRoles[] = [
                'id' => $project->id,
                'role' => $otherRole
            ];

            factory(ProjectRole::class)->create([
                'user_id' => $anotherUser->id,
                'project_id' => $project->id,
                'role' => $role
            ]);
            factory(ProjectRole::class)->create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => $otherRole
            ]);

            //assert the project is present
            $this->assertEquals(1, Project::where('id', $project->id)->count());
            //assert user roles for that project
            $this->assertEquals(2, ProjectRole::where('project_id', $project->id)->count());
            $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $anotherUser->id)->value('role'));
            $this->assertEquals($otherRole, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));
        }


        //4 delete user account
        Mail::fake();
        $this->actingAs($user, self::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        //assert user was archived
        $this->assertEquals(0, User::where('email', $user->email)->count());
        $this->assertEquals(1, User::where('id', $user->id)->count());

        //assert provider is removed
        $this->assertEquals(0, UserProvider::where('email', $user->email)
            ->count());
        $this->assertEquals(0, UserProvider::where('user_id', $user->id)
            ->count());

        //assert projects with CREATOR role were removed
        $this->assertEquals(0, Project::where('id', $projectRoleCreatorOne->id)
            ->count());
        $this->assertEquals(0, Project::where('id', $projectRoleCreatorTwo->id)
            ->count());

        //assert all roles are dropped on removed projects
        $this->assertEquals(0, ProjectRole::where('project_id', $projectRoleCreatorOne->id)->count());
        $this->assertEquals(0, ProjectRole::where('project_id', $projectRoleCreatorTwo->id)->count());

        foreach ($projectsWithOtherRoles as $projectWithOtherRole) {
            //assert projects with other roles are NOT archived
            $projectId = $projectWithOtherRole['id'];
            $this->assertEquals(0, Project::where('id', $projectId)
                ->where('status', 'archived')
                ->count());

            $this->assertEquals(1, Project::where('id', $projectId)
                ->where('status', '<>', 'archived')
                ->count());

            //assert all other roles are dropped, but not creator
            $this->assertEquals(0, ProjectRole::where('project_id', $projectId)
                ->where('role', '!=', $role)
                ->count());
            $this->assertEquals(1, ProjectRole::where('project_id', $projectId)
                ->where('role', $role)
                ->count());
        }

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->assertSeeInHtml($user->email);
        });

        //check counts
        $this->assertEquals($projectsCount + 4, Project::count());
        $this->assertEquals($entriesCount, Entry::count());
        $this->assertEquals($branchEntriesCount, BranchEntry::count());
        $this->assertEquals($projectStatsCount, ProjectStats::count());
    }
}
