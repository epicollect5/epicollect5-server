<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use ec5\Http\Controllers\Web\Project\ProjectDeleteController;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\BranchEntryArchive;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\EntryArchive;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectFeatured;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStat;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Projects\Project as LegacyProject;
use ec5\Models\ProjectRoles\ProjectRole as LegacyProjectRole;
use ec5\Models\Users\User as LegacyUser;
use ec5\Models\Projects\ProjectDefinition;
use ec5\Models\Projects\ProjectExtra;
use ec5\Models\Projects\ProjectMapping;
use ec5\Models\Projects\ProjectStats;
use ec5\Models\Eloquent\User;
use ec5\Repositories\QueryBuilder\Project\SearchRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Request;
use Tests\TestCase;
use Mockery;
use Config;

class ProjectDeleteEntriesControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delete_entries_page_renders_correctly()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        $role = \Illuminate\Support\Facades\Config::get('ec5Strings.project_roles.creator');
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStat::class)->create(
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
            ->get('myprojects/' . $project->slug . '/delete-entries')
            ->assertStatus(200);
    }


    public function test_soft_delete()
    {
        //creator
        $role = Config::get('ec5Strings.project_roles.creator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();

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

        //assert project is present
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        // Act: Simulate the execution of the softDelete method
        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/' . $project->slug . '/delete-entries', [
                'project-name' => $project->name
            ]);


        //Check if the redirect is successful
        $response->assertRedirect('/myprojects/' . $project->slug . '/manage-entries');

        //assert entries & branch entries are archived
        $this->assertEquals(0, Entry::where('project_id', $project->id)->count());
        $this->assertEquals(0, BranchEntry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries, EntryArchive::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntryArchive::where('project_id', $project->id)->count());
        //assert roles are not dropped
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        // You can also check for messages in the session
        $response->assertSessionHas('message', 'ec5_122');

        //check counts
        $this->assertEquals($projectsCount + 1, Project::count());
        $this->assertEquals($entriesCount, Entry::count());
        $this->assertEquals($branchEntriesCount, BranchEntry::count());
    }

    public function test_soft_delete_when_role_is_manager()
    {
        //manager
        $role = Config::get('ec5Strings.project_roles.manager');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();
        //create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
        //assign the user to that project with the curator role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is curator
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/' . $project->slug . '/delete-entries', [
                'project-name' => strrev($project->name)//just scramble project name
            ]);

        //fails as manager role cannot delete entries in bulk
        $response->assertRedirect('myprojects/' . $project->slug . '/manage-entries');
        $this->assertEquals('ec5_91', session('errors')->getBag('default')->first());
    }

    public function test_soft_delete_but_project_name_is_missing()
    {
        //creator
        $role = Config::get('ec5Strings.project_roles.creator');
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

        //assert project is present
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/' . $project->slug . '/delete-entries', [
                // '_token' => csrf_token()
            ]);

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

    public function test_soft_delete_but_project_name_is_wrong()
    {
        //creator
        $role = Config::get('ec5Strings.project_roles.creator');
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

        //assert project is present
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/' . $project->slug . '/delete-entries', [
                'project-name' => strrev($project->name)//just scramble project name
            ]);

        //fails as project name is wrong
        $response->assertRedirect('myprojects/' . $project->slug . '/manage-entries');
        $this->assertEquals('ec5_91', session('errors')->getBag('default')->first());

    }

    public function test_soft_delete_but_role_is_curator()
    {
        //creator
        $role = Config::get('ec5Strings.project_roles.curator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();
        //create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
        //assign the user to that project with the curator role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is curator
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/' . $project->slug . '/delete-entries', [
                'project-name' => strrev($project->name)//just scramble project name
            ]);

        //fails as curator role cannot delete entries in bulk
        $response->assertRedirect('myprojects/' . $project->slug . '/manage-entries');
        $this->assertEquals('ec5_91', session('errors')->getBag('default')->first());
    }

    public function test_soft_delete_but_role_is_collector()
    {
        //collector
        $role = Config::get('ec5Strings.project_roles.collector');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();
        //create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
        //assign the user to that project with the curator role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is curator
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/' . $project->slug . '/delete-entries', [
                'project-name' => strrev($project->name)//just scramble project name
            ]);

        //fails as curator role cannot delete entries in bulk
        $response->assertRedirect('myprojects/' . $project->slug . '/manage-entries');
        $this->assertEquals('ec5_91', session('errors')->getBag('default')->first());
    }

    public function test_soft_delete_but_role_is_viewer()
    {
        //viewer
        $role = Config::get('ec5Strings.project_roles.viewer');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();
        //create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
        //assign the user to that project with the curator role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is curator
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/' . $project->slug . '/delete-entries', [
                'project-name' => strrev($project->name)//just scramble project name
            ]);

        //fails as viewer role cannot delete entries in bulk
        $response->assertRedirect('myprojects/' . $project->slug . '/manage-entries');
        $this->assertEquals('ec5_91', session('errors')->getBag('default')->first());
    }

    public function test_project_stats_is_updated()
    {
        //creator
        $role = Config::get('ec5Strings.project_roles.creator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);

        //get existing counts
        $projectsCount = Project::count();
        $entriesCount = Entry::count();
        $branchEntriesCount = BranchEntry::count();

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

        //assert the project is present
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert the user role is CREATOR
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        // Act: Simulate the execution of the softDelete method
        $response = $this->actingAs($user, self::DRIVER)
            ->post('myprojects/' . $project->slug . '/delete-entries', [
                'project-name' => $project->name
            ]);


        //Check if the redirect is successful
        $response->assertRedirect('/myprojects/' . $project->slug . '/manage-entries');

        //assert entries & branch entries are archived
        $this->assertEquals(0, Entry::where('project_id', $project->id)->count());
        $this->assertEquals(0, BranchEntry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfEntries, EntryArchive::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntryArchive::where('project_id', $project->id)->count());
        //assert roles are not dropped
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        // You can also check for messages in the session
        $response->assertSessionHas('message', 'ec5_122');

        //assert stats are updated
        $this->assertEquals(0, ProjectStat::where('project_id', $project->id)->value('total_entries'));
        $this->assertDatabaseHas('project_stats', [
            'project_id' => $project->id,
            'form_counts->0' => null, // This ensures the first element of the array is null, meaning the array is empty
            'branch_counts->0' => null, // This ensures the first element of the array is null, meaning the array is empty
        ]);

        //check counts
        $this->assertEquals($projectsCount + 1, Project::count());
        $this->assertEquals($entriesCount, Entry::count());
        $this->assertEquals($branchEntriesCount, BranchEntry::count());
    }

    public function test_exception()
    {
        //todo:
    }
}

