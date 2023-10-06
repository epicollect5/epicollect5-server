<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use ec5\Http\Controllers\Web\Project\ProjectDeleteController;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\Entry;
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
use ec5\Models\Users\User;
use ec5\Repositories\QueryBuilder\Project\SearchRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Request;
use Tests\TestCase;
use Mockery;
use Config;

class ProjectDeleteControllerTest extends TestCase
{
    use DatabaseTransactions;

    // use WithoutMiddleware;

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

    public function test_soft_delete()
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        //:log user in

        // Act: Simulate the execution of the softDelete method
        //$this->withoutMiddleware();
        $response = $this->actingAs($user, SELF::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token()
            ]);


        //Check if the redirect is successful
        $response->assertRedirect('/myprojects');
        //Check if the project is deleted
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        //Check if the project is archived
        $this->assertDatabaseHas('projects_archive', ['id' => $project->id]);

        //assert entries & branch entries are NOT touched
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $project->id)->count());
        //assert roles are dropped
        $this->assertEquals(0, ProjectRole::where('project_id', $project->id)->count());
        // You can also check for messages in the session
        $response->assertSessionHas('message', 'ec5_114');
    }

    public function test_soft_delete_missing_permission_as_manager()
    {
        //manager
        $role = Config::get('ec5Strings.project_roles.manager');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();
        //create mock project with that another user
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

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is MANAGER
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the softDelete method
        //$this->withoutMiddleware();
        $response = $this->actingAs($user, SELF::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token()
            ]);
        //Bail out since user has no permission
        $response->assertRedirect('myprojects/' . $project->slug);
        //Check if the project is NOT archived
        $this->assertDatabaseMissing('projects_archive', ['id' => $project->id]);
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

    public function test_soft_delete_missing_permission_as_curator()
    {
        //curator
        $role = Config::get('ec5Strings.project_roles.curator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();
        //create mock project with that another user
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
        //assign another user to that project with the CREATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $anotherUser->id,
            'project_id' => $project->id,
            'role' => 'creator'
        ]);
        //assign the user to that project with the CURATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is CURATOR
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the softDelete method
        //$this->withoutMiddleware();
        $response = $this->actingAs($user, SELF::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token()
            ]);
        //Bail out since user has no permission
        $response->assertRedirect('myprojects/' . $project->slug);
        //Check if the project is NOT archived
        $this->assertDatabaseMissing('projects_archive', ['id' => $project->id]);
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

    public function test_soft_delete_missing_permission_as_collector()
    {
        //collector
        $role = Config::get('ec5Strings.project_roles.collector');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();
        //create mock project with that another user
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
        //assign another user to that project with the CREATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $anotherUser->id,
            'project_id' => $project->id,
            'role' => 'creator'
        ]);
        //assign the user to that project with the COLLECTOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is COLLECTOR
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the softDelete method
        //$this->withoutMiddleware();
        $response = $this->actingAs($user, SELF::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token()
            ]);
        //Bail out since user has no permission
        $response->assertRedirect('myprojects/' . $project->slug);
        //Check if the project is NOT archived
        $this->assertDatabaseMissing('projects_archive', ['id' => $project->id]);
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

    public function test_soft_delete_missing_permission_as_viewer()
    {
        //viewer
        $role = Config::get('ec5Strings.project_roles.viewer');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();
        //create mock project with that another user
        $project = factory(Project::class)->create(['created_by' => $anotherUser->id]);
        //assign another user to that project with the CREATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $anotherUser->id,
            'project_id' => $project->id,
            'role' => 'creator'
        ]);
        //assign the user to that project with the VIEWER role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //assert project is present before archiving
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is VIEWER
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the softDelete method
        //$this->withoutMiddleware();
        $response = $this->actingAs($user, SELF::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token()
            ]);
        //Bail out since user has no permission
        $response->assertRedirect('myprojects/' . $project->slug);
        //Check if the project is NOT archived
        $this->assertDatabaseMissing('projects_archive', ['id' => $project->id]);
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

    public function test_soft_delete_but_project_is_featured()
    {
        //CREATOR
        $role = Config::get('ec5Strings.project_roles.creator');
        $numOfEntries = mt_rand(10, 100);
        $numOfBranchEntries = mt_rand(10, 100);
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        //create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);
        //assign another user to that project with the CREATOR role
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        //flag the project as featured
        factory(ProjectFeatured::class)->create(['project_id' => $project->id]);

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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );
        // Act: Simulate the execution of the softDelete method
        //$this->withoutMiddleware();
        $response = $this->actingAs($user, SELF::DRIVER)
            ->post('/myprojects/' . $project->slug . '/delete', [
                '_token' => csrf_token()
            ]);
        //Bail out since the project is featured
        $response->assertRedirect('myprojects/' . $project->slug);
        //Check if the project is NOT archived
        $this->assertDatabaseMissing('projects_archive', ['id' => $project->id]);
        //Check if the project is NOT deleted
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        //assert entries & branch entries are NOT touched
        $this->assertEquals($numOfEntries, Entry::where('project_id', $project->id)->count());
        $this->assertEquals($numOfBranchEntries * $numOfEntries, BranchEntry::where('project_id', $project->id)->count());
        //assert roles are NOT dropped
        $this->assertEquals(1, ProjectRole::where('project_id', $project->id)->count());
        //assert error code is sent back to user
        $this->assertEquals('ec5_221', session('errors')->getBag('default')->first());
    }

    public function test_softDelete_with_exception()
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
        factory(ProjectStat::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        // Create an instance of the controller
//        $controller = new ProjectDeleteController(new Request());
//
//        // Create a partial mock with only the archiveProject method mocked
//        $controllerMock = Mockery::mock($controller)->makePartial();
//
//        //Mock only the archiveProject method
//        $controllerMock->shouldReceive('archiveProject')
//            ->with($project->id, $project->slug)
//            ->once()
//            ->andReturn(false);

        // Use the mocked instance to hit the real controller method
        //todo: get back to this after the refactoring using DTO instead of middleware
        // $response = $controllerMock->softDelete($project->id, $project->slug);

        // Mock the middleware behavior
//        $legacyProject = new LegacyProject(
//            new ProjectDefinition(),
//            new ProjectExtra(),
//            new ProjectMapping(),
//            new ProjectStats()
//        );
//        $legacyProjectRole = new LegacyProjectRole();
//        $legacyProjectRole->setRole(new LegacyUser(), $project->id, $role);
//
//        // Retrieve project (legacy way,  R&A fiasco)
//        $search = new SearchRepository();
//        $currentProject = $search->find($project->slug, $columns = array('*'));
//        $legacyProject->init($currentProject);
//
//        $request = $this->app['request'];
//        // Set attributes as if the middleware has run
//        $request->attributes->add(['requestedProject' => $legacyProject]);
//        //$this->request->attributes->add(['requestedUser' => $this->requestedUser]);
//        $request->attributes->add(['requestedProjectRole' => $legacyProjectRole]);
//
//
//        //$controller->requestedProject = $legacyProject;
//        //$controller->requestedProjectRole = $legacyProjectRole;
////
////        // Create a partial mock with only the archiveProject method mocked
//        $controllerMock = Mockery::mock(new ProjectControllerBase($request))->makePartial();
//
////        //Mock only the archiveProject method
//        $controllerMock->shouldReceive('archiveProject')
//            ->with($project->id, $project->slug)
//            ->andReturn(false);
//
//        // Use the mocked instance to hit the real controller method
//        // Create an instance of the controller
//        $controller = new ProjectDeleteController($request);
//        $response = $controller->softDelete();
//
//        dd($response->status());

        //Bail out since the project is featured
        // $response->assertRedirect('myprojects/' . $project->slug);
        // $this->assertEquals('ec5_104', session('errors')->getBag('default')->first());

    }
}

