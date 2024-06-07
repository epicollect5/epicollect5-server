<?php

namespace Tests\Http\Controllers\Web\Project;

use Auth;
use ec5\DTO\ProjectDTO;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

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
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'status' => config('epicollect.strings.project_status.locked')
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        $projectStats = factory(ProjectStats::class)->create(
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

        $this->assertEquals('project.project_delete_entries', $response->original->getName());
        // Assert the view has the correct variables
        $response->assertViewHas('project');
        $projectDTOInstance = $response->original->getData()['project'];
        // Assert that the 'project' variable is an instance of ProjectDTO
        $this->assertInstanceOf(ProjectDTO::class, $projectDTOInstance);
        $response->assertViewHas('totalEntries', $projectStats->total_entries);
    }

    public function test_delete_entries_page_error_if_project_not_locked()
    {
        //create mock user
        $user = factory(User::class)->create();
        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'status' => config('epicollect.strings.project_status.active')
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
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
            ->actingAs($user, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/delete-entries')
            ->assertStatus(200);

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

    public function test_delete_entries_page_redirect_if_not_logged_in()
    {
        //create mock user
        $user = factory(User::class)->create();
        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);
        //assign the user to that project with the CREATOR role
        $role = config('epicollect.strings.project_roles.creator');
        factory(ProjectRole::class)->create([
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

        Auth::logout();
        $this
            ->get('myprojects/' . $project->slug . '/delete-entries')
            ->assertStatus(302)
            ->assertRedirect(Route('login'));
    }

    public function test_delete_entries_page_when_role_is_manager()
    {
        //manager
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $manager = factory(User::class)->create();
        //create mock project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'status' => config('epicollect.strings.project_status.locked')
        ]);
        //assign the user to that project with the manager role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        //assert project is present
        $this->assertEquals(1, Project::where('id', $project->id)->count());
        //assert user role  is curator
        $this->assertEquals(2, ProjectRole::where('project_id', $project->id)->count());
        $this->assertEquals(1, ProjectRole::where('user_id', $user->id)->count());
        $this->assertEquals(config('epicollect.strings.project_roles.manager'), ProjectRole::where('project_id', $project->id)->where('user_id', $manager->id)->value('role'));

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        $projectStats = factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($manager, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/delete-entries');

        $this->assertEquals('project.project_delete_entries', $response->original->getName());
        // Assert the view has the correct variables
        $response->assertViewHas('project');
        $projectDTOInstance = $response->original->getData()['project'];
        // Assert that the 'project' variable is an instance of ProjectDTO
        $this->assertInstanceOf(ProjectDTO::class, $projectDTOInstance);
        $response->assertViewHas('totalEntries', $projectStats->total_entries);
    }

    public function test_delete_entries_page_but_role_is_curator()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $curator = factory(User::class)->create();
        //create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //assign the user to that project with the curator role
        factory(ProjectRole::class)->create([
            'user_id' => $curator->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.curator')
        ]);

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($curator, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/delete-entries');

        //fails as curator role cannot delete entries in bulk
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

    public function test_delete_entries_page_but_role_is_collector()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $collector = factory(User::class)->create();
        //create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //assign the user to that project with the curator role
        factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($collector, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/delete-entries');

        //fails as curator role cannot delete entries in bulk
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

    public function test_delete_entries_page_but_role_is_viewer()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $viewer = factory(User::class)->create();
        //create mock project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //assign the user to that project with the curator role
        factory(ProjectRole::class)->create([
            'user_id' => $viewer->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.viewer')
        ]);

        //set up project stats and project structures (to make R&A middleware work, to be removed)
        //because they are using a repository with joins
        factory(ProjectStats::class)->create(
            ['project_id' => $project->id]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $response = $this->actingAs($viewer, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/delete-entries');

        //fails as curator role cannot delete entries in bulk
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

}

