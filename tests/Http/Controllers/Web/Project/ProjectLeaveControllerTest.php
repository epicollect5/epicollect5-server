<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\OAuth\OAuthClientProject;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class ProjectLeaveControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_leave_page_forbidden_to_creator()
    {
        //create mock user
        $creator = factory(User::class)->create();
        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        $response = $this
            ->actingAs($creator, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/leave');
        //Bail out since creator cannot leave a project
        $response->assertStatus(200);
        $this->assertEquals('errors.gen_error', $response->original->getName());
        // Assert that there is an error message with key 'ec5_91'
        $this->assertArrayHasKey('errors', $response->original->getData());
        // Assert that the view has an 'errors' variable
        $this->assertTrue($response->original->offsetExists('errors'));
        // Access the MessageBag and assert specific errors
        $errors = $response->original->offsetGet('errors');
        // Ensure that the 'errors' key exists in the MessageBag
        $this->assertTrue($errors->has('errors'));
        // Access the 'errors' array directly
        $errorsArray = $errors->get('errors');
        // Assert that it is an array and contains 'ec5_91'
        $this->assertInternalType('array', $errorsArray);
        $this->assertEquals('ec5_91', $errorsArray[0]);
    }

    public function test_leave_page_renders_correctly_for_manager()
    {
        //create mock user
        $creator = factory(User::class)->create();
        $user = factory(User::class)->create();
        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $creator->id]);
        //assign the user to that project with the manager role
        $role = config('epicollect.strings.project_roles.manager');
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);
        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
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
            ->get('myprojects/' . $project->slug . '/leave')
            ->assertStatus(200);
    }

    public function test_leave_page_renders_correctly_for_curator()
    {
        //create mock user
        $creator = factory(User::class)->create();
        $user = factory(User::class)->create();
        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $creator->id]);
        //assign the user to that project with the curator role
        $role = config('epicollect.strings.project_roles.curator');
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);
        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
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
            ->get('myprojects/' . $project->slug . '/leave')
            ->assertStatus(200);
    }

    public function test_leave_page_renders_correctly_for_collector()
    {
        //create mock user
        $creator = factory(User::class)->create();
        $user = factory(User::class)->create();
        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $creator->id]);
        //assign the user to that project with the collector role
        $role = config('epicollect.strings.project_roles.collector');
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);
        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
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
            ->get('myprojects/' . $project->slug . '/leave')
            ->assertStatus(200);
    }

    public function test_leave_page_renders_correctly_for_viewer()
    {
        //create mock user
        $creator = factory(User::class)->create();
        $user = factory(User::class)->create();
        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $creator->id]);
        //assign the user to that project with the viewer role
        $role = config('epicollect.strings.project_roles.viewer');
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);
        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
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
            ->get('myprojects/' . $project->slug . '/leave')
            ->assertStatus(200);
    }

    public function test_leave_post_request_but_project_name_missing()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $numOfEntries = mt_rand(1, 5);
        $numOfBranchEntries = mt_rand(1, 5);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        //create a mock project with that user
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
        //add mock entries & branch entries to mock project
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

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => $numOfEntries
            ]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        factory(OAuthClientProject::class)->create(
            ['project_id' => $project->id]
        );

        // Act: Simulate the execution of the softDelete method
        //$this->withoutMiddleware();
        $response = $this->actingAs($user, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/leave', [
                '_token' => csrf_token()
            ]);

        //Check if the redirect is successful
        $response->assertRedirect('myprojects/' . $project->slug . '/leave');
        $this->assertEquals('ec5_103', session('errors')->getBag('default')->first());
    }

    public function test_leave_post_request_but_project_name_does_not_match()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $numOfEntries = mt_rand(1, 5);
        $numOfBranchEntries = mt_rand(1, 5);

        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        //create a mock project with that user
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
        //add mock entries & branch entries to mock project
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

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => $numOfEntries
            ]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        factory(OAuthClientProject::class)->create(
            ['project_id' => $project->id]
        );

        // Act: Simulate the execution of the softDelete method
        $response = $this->actingAs($user, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/leave', [
                '_token' => csrf_token(),
                'project-name' => $project->name . ' fail'
            ]);

        //Check if the redirect is successful
        $response->assertRedirect('/myprojects/' . $project->slug . '/leave');
        $this->assertEquals('ec5_21', session('errors')->getBag('default')->first());
    }

    public function test_leave_missing_permission_as_creator()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $numOfEntries = mt_rand(1, 5);
        $numOfBranchEntries = mt_rand(1, 5);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();
        //create a mock project with that another user
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
        //assign another user to that project with the CREATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $anotherUser->id,
            'project_id' => $project->id,
            'role' => 'creator'
        ]);
        //assign the user to that project with the MANAGER role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert the project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert the user role is creator
        $this->assertEquals(2, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $user->id)->value('role'));
        //add mock entries & branch entries to mock project
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
        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the leave method
        $response = $this->actingAs($user, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/leave', [
                '_token' => csrf_token(),
                'project-name' => $project->name
            ]);
        //Bail out since user has no permission
        $response->assertRedirect('myprojects/' . $project->slug . '/leave');
        //Check if the project is NOT deleted
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        //assert entries & branch entries are NOT touched
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $project->id)->count());
        //assert roles are NOT dropped
        $this->assertEquals(2, ProjectRole::where('project_id', $project->id)->count());
        //assert error code is sent back to user
        $this->assertEquals('ec5_91', session('errors')->getBag('default')->first());
    }

    public function test_leave_performed_as_manager()
    {
        //creator
        $role = config('epicollect.strings.project_roles.manager');
        $numOfEntries = mt_rand(1, 5);
        $numOfBranchEntries = mt_rand(1, 5);
        //create a fake user and save it to DB
        $creator = factory(User::class)->create();
        $manager = factory(User::class)->create();
        //create a mock project with that another user
        $project = factory(Project::class)->create(['created_by' => $creator->id]);
        //assign another user to that project with the manager role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => 'creator'
        ]);
        //assign the user to that project with the creator role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert the user role is manager
        $this->assertEquals(2, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $manager->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $manager->id)->value('role'));
        //add mock entries & branch entries to mock project
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
        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the leave method
        $response = $this->actingAs($manager, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/leave', [
                '_token' => csrf_token(),
                'project-name' => $project->name
            ]);

        $response->assertRedirect('myprojects');
        //Check if the project is NOT deleted
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        //assert entries & branch entries are NOT touched
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $project->id)->count());
        //assert the manager role is dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)
            ->where('user_id', $manager->id)->count());
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)
            ->where('user_id', $creator->id)
            ->count());

        //assert success is sent back to user
        $response->assertSessionHas('message', 'ec5_396');
    }

    public function test_leave_performed_as_curator()
    {
        //creator
        $role = config('epicollect.strings.project_roles.curator');
        $numOfEntries = mt_rand(1, 5);
        $numOfBranchEntries = mt_rand(1, 5);
        //create a fake user and save it to DB
        $creator = factory(User::class)->create();
        $curator = factory(User::class)->create();
        //create a mock project with that another user
        $project = factory(Project::class)->create(['created_by' => $creator->id]);
        //assign another user to that project with the manager role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => 'creator'
        ]);
        //assign the user to that project with the creator role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $curator->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert the user role is manager
        $this->assertEquals(2, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $curator->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $curator->id)->value('role'));
        //add mock entries & branch entries to mock project
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
        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the leave method
        $response = $this->actingAs($curator, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/leave', [
                '_token' => csrf_token(),
                'project-name' => $project->name
            ]);

        $response->assertRedirect('myprojects');
        //Check if the project is NOT deleted
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        //assert entries & branch entries are NOT touched
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $project->id)->count());
        //assert the manager role is dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)
            ->where('user_id', $curator->id)->count());
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)
            ->where('user_id', $creator->id)
            ->count());

        //assert success is sent back to user
        $response->assertSessionHas('message', 'ec5_396');
    }

    public function test_leave_performed_as_collector()
    {
        //creator
        $role = config('epicollect.strings.project_roles.collector');
        $numOfEntries = mt_rand(2, 5);
        $numOfBranchEntries = mt_rand(1, 3);
        //create a fake user and save it to DB
        $creator = factory(User::class)->create();
        $collector = factory(User::class)->create();
        //create a mock project with that another user
        $project = factory(Project::class)->create(['created_by' => $creator->id]);
        //assign another user to that project with the manager role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => 'creator'
        ]);
        //assign the user to that project with the creator role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert the user role is manager
        $this->assertEquals(2, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $collector->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $collector->id)->value('role'));
        //add mock entries & branch entries to mock project
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
        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the leave method
        $response = $this->actingAs($collector, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/leave', [
                '_token' => csrf_token(),
                'project-name' => $project->name
            ]);

        $response->assertRedirect('myprojects');
        //Check if the project is NOT deleted
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        //assert entries & branch entries are NOT touched
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $project->id)->count());
        //assert the manager role is dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)
            ->where('user_id', $collector->id)->count());
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)
            ->where('user_id', $creator->id)
            ->count());

        //assert success is sent back to user
        $response->assertSessionHas('message', 'ec5_396');
    }

    public function test_leave_performed_as_viewer()
    {
        //creator
        $role = config('epicollect.strings.project_roles.viewer');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $creator = factory(User::class)->create();
        $viewer = factory(User::class)->create();
        //create a mock project with that another user
        $project = factory(Project::class)->create(['created_by' => $creator->id]);
        //assign another user to that project with the manager role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => 'creator'
        ]);
        //assign the user to that project with the creator role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $viewer->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert the user role is manager
        $this->assertEquals(2, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $viewer->id)->count());
        $this->assertEquals($role, ProjectRole::where('project_id', $project->id)->where('user_id', $viewer->id)->value('role'));
        //add mock entries & branch entries to mock project
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
        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the leave method
        $response = $this->actingAs($viewer, self::DRIVER)
            ->post('/myprojects/' . $project->slug . '/leave', [
                '_token' => csrf_token(),
                'project-name' => $project->name
            ]);
        $response->assertRedirect('myprojects');
        //Check if the project is NOT deleted
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        //assert entries & branch entries are NOT touched
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $project->id)->count());
        //assert the manager role is dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)
            ->where('user_id', $viewer->id)->count());
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)
            ->where('user_id', $creator->id)
            ->count());

        //assert success is sent back to user
        $response->assertSessionHas('message', 'ec5_396');
    }

    //todo: need refactoring
    public function test_leave_with_exception()
    {
        //creator
        $role = config('epicollect.strings.project_roles.creator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        //create mock project with that user
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
        //add mock entries & branch entries to mock project
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

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
    }
}

