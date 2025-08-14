<?php

namespace Tests\Http\Controllers\Web\Project;

use Auth;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use DatabaseTransactions;

    public const string DRIVER = 'web';

    public function test_private_project_home_page_renders_correctly()
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

        $response = $this
             ->actingAs($user, self::DRIVER)
             ->get('project/' . $project->slug)
             ->assertStatus(200);

        $response->assertSee('Details');
    }

    public function test_private_project_home_page_redirect_if_not_logged_in()
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

        $this
            ->get('project/' . $project->slug)
            ->assertStatus(302)
            ->assertRedirect(Route('login'));
    }

    public function test_public_project_home_page_renders_correctly()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.public')
        ]);

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

        $response = $this
            ->get('project/' . $project->slug)
            ->assertStatus(200);

        $response->assertDontSee('Details');

    }

    public function test_public_project_home_error_project_trashed()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.public'),
            'status' => config('epicollect.strings.project_status.trashed')
        ]);

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

        $response = $this
            ->get('project/' . $project->slug)
            ->assertStatus(200);
        $this->assertEquals('errors.gen_error', $response->original->getName());
        // Assert that there is an error message with key 'ec5_11'
        $this->assertArrayHasKey('errors', $response->original->getData());
        // Assert that the view has an 'errors' variable
        $this->assertTrue($response->original->offsetExists('errors'));
        // Access the MessageBag and assert specific errors
        $errors = $response->original->offsetGet('errors');
        // Ensure that the 'view' key exists in the MessageBag
        $this->assertTrue($errors->has('view'));
        // Access the 'errors' array directly
        $errorsArray = $errors->get('view');
        // Assert that it is an array and contains 'ec5_11'
        $this->assertIsArray($errorsArray);
        $this->assertEquals('ec5_11', $errorsArray[0]);
    }

    public function test_public_project_dataviewer_error_project_trashed()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.public'),
            'status' => config('epicollect.strings.project_status.trashed')
        ]);

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

        $response = $this
            ->get('project/' . $project->slug. '/data')
            ->assertStatus(200);
        $this->assertEquals('errors.gen_error', $response->original->getName());
        // Assert that there is an error message with key 'ec5_11'
        $this->assertArrayHasKey('errors', $response->original->getData());
        // Assert that the view has an 'errors' variable
        $this->assertTrue($response->original->offsetExists('errors'));
        // Access the MessageBag and assert specific errors
        $errors = $response->original->offsetGet('errors');
        // Ensure that the 'view' key exists in the MessageBag
        $this->assertTrue($errors->has('view'));
        // Access the 'errors' array directly
        $errorsArray = $errors->get('view');
        // Assert that it is an array and contains 'ec5_11'
        $this->assertIsArray($errorsArray);
        $this->assertEquals('ec5_11', $errorsArray[0]);
    }

    public function test_public_project_home_page_renders_correctly_logged_in()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.public')
        ]);

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

        $this
            ->actingAs($user)
            ->get('project/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_project_details_page_renders_correctly_with_server_role_basic()
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

        /*
        set up project stats and project structures (to make R&A middleware work, to be removed)
        because they are using a repository with joins
        */
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $this
            ->actingAs($user, self::DRIVER)
            ->get('myprojects/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_project_details_page_renders_redirects_not_logged_in()
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

        /*
        set up project stats and project structures (to make R&A middleware work, to be removed)
        because they are using a repository with joins
        */
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
            ->get('myprojects/' . $project->slug)
            ->assertStatus(302)
            ->assertRedirect(Route('login'));
    }

    public function test_project_details_page_renders_correctly_with_server_role_superadmin()
    {
        //create mock user
        $user = factory(User::class)->create(
            ['server_role' => config('epicollect.strings.server_roles.superadmin')]
        );

        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        $role = config('epicollect.strings.project_roles.creator');
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        /*
        set up project stats and project structures (to make R&A middleware work, to be removed)
        because they are using a repository with joins
        */
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $this
            ->actingAs($user, self::DRIVER)
            ->get('myprojects/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_project_details_page_renders_correctly_with_server_role_admin()
    {
        //create mock user
        $user = factory(User::class)->create(
            ['server_role' => config('epicollect.strings.server_roles.admin')]
        );

        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        $role = config('epicollect.strings.project_roles.creator');
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        /*
        set up project stats and project structures (to make R&A middleware work, to be removed)
        because they are using a repository with joins
        */
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        factory(ProjectStructure::class)->create(
            ['project_id' => $project->id]
        );

        $this
            ->actingAs($user, self::DRIVER)
            ->get('myprojects/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_dataviewer_page_renders_correctly()
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

        $this
            ->actingAs($user, self::DRIVER)
            ->get('project/' . $project->slug . '/data')
            ->assertStatus(200);
    }

    public function test_formbuilder_page_renders_correctly_creator()
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

        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get(Route('formbuilder', ['project_slug' => $project->slug]))
            ->assertStatus(200);


        // Assert that the 'requestAttributes' variable exists in the view data
        $this->assertEquals(
            $project->name,
            $this->app['view']->getShared()['requestAttributes']->requestedProject->name
        ); // Check the data passed to the view
        $this->assertEquals('project.formbuilder', $response->original->getName());
    }

    public function test_formbuilder_page_renders_correctly_manager()
    {
        //create mock user
        $user = factory(User::class)->create();
        $creator = factory(User::class)->create();
        //create a fake project with the creator
        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        //add the manager role to user
        factory(ProjectRole::class)->create(
            [
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => config('epicollect.strings.project_roles.manager')
            ]
        );

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
            ->get(Route('formbuilder', ['project_slug' => $project->slug]))
            ->assertStatus(200);

        // Assert that the 'requestAttributes' variable exists in the view data
        $this->assertEquals(
            $project->name,
            $this->app['view']->getShared()['requestAttributes']->requestedProject->name
        ); // Check the data passed to the view
        $this->assertEquals('project.formbuilder', $response->original->getName());

    }

    public function test_formbuilder_page_forbidden_to_curator()
    {
        //create mock user
        $user = factory(User::class)->create();
        $creator = factory(User::class)->create();
        //create a fake project with the creator
        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        //add the manager role to user
        factory(ProjectRole::class)->create(
            [
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => config('epicollect.strings.project_roles.curator')
            ]
        );

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
            ->get(Route('formbuilder', ['project_slug' => $project->slug]))
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
        $this->assertIsArray($errorsArray);
        $this->assertEquals('ec5_91', $errorsArray[0]);


    }

    public function test_formbuilder_page_forbidden_to_collector()
    {
        //create mock user
        $user = factory(User::class)->create();
        $creator = factory(User::class)->create();
        //create a fake project with the creator
        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        //add the manager role to user
        factory(ProjectRole::class)->create(
            [
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => config('epicollect.strings.project_roles.collector')
            ]
        );

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
            ->get(Route('formbuilder', ['project_slug' => $project->slug]))
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
        $this->assertIsArray($errorsArray);
        $this->assertEquals('ec5_91', $errorsArray[0]);


    }

    public function test_formbuilder_page_forbidden_to_viewer()
    {
        //create mock user
        $user = factory(User::class)->create();
        $creator = factory(User::class)->create();
        //create a fake project with the creator
        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        //add the manager role to user
        factory(ProjectRole::class)->create(
            [
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => config('epicollect.strings.project_roles.viewer')
            ]
        );

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
            ->get(Route('formbuilder', ['project_slug' => $project->slug]))
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
        $this->assertIsArray($errorsArray);
        $this->assertEquals('ec5_91', $errorsArray[0]);
    }

    public function test_formbuilder_page_forbidden_to_guests()
    {
        //create mock user
        $user = factory(User::class)->create();
        $creator = factory(User::class)->create();
        //create a fake project with the creator
        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        //add the manager role to user
        factory(ProjectRole::class)->create(
            [
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => config('epicollect.strings.project_roles.viewer')
            ]
        );

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
            ->get(Route('formbuilder', ['project_slug' => $project->slug]))
            ->assertStatus(302)
            ->assertRedirect(Route('login'));
    }

    public function test_download_project_definition()
    {
        //create mock user
        $user = factory(User::class)->create();
        //create a fake project with the creator
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //add the role to
        factory(ProjectRole::class)->create(
            [
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => config('epicollect.strings.project_roles.creator')
            ]
        );

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

        $this
            ->actingAs($user, self::DRIVER)
            ->get('myprojects/' . $project->slug . '/download-project-definition')
            ->assertStatus(200);
    }

    public function test_open_project_page_renders_correctly_public_not_logged()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.public')
        ]);

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

        $this
            ->get('open/project/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_open_project_page_renders_error_because_trashed()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.public'),
            'status' => config('epicollect.strings.project_status.trashed')
        ]);

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

        $response = $this
            ->get('open/project/' . $project->slug)
            ->assertStatus(200);
        $this->assertEquals('errors.gen_error', $response->original->getName());
        // Assert that there is an error message with key 'ec5_11'
        $this->assertArrayHasKey('errors', $response->original->getData());
        // Assert that the view has an 'errors' variable
        $this->assertTrue($response->original->offsetExists('errors'));
        // Access the MessageBag and assert specific errors
        $errors = $response->original->offsetGet('errors');
        // Ensure that the 'view' key exists in the MessageBag
        $this->assertTrue($errors->has('view'));
        // Access the 'errors' array directly
        $errorsArray = $errors->get('view');
        // Assert that it is an array and contains 'ec5_11'
        $this->assertIsArray($errorsArray);
        $this->assertEquals('ec5_11', $errorsArray[0]);
    }

    public function test_open_project_page_renders_correctly_public_logged_in()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.public')
        ]);

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

        $this->actingAs($user)
            ->get('open/project/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_open_project_page_renders_correctly_private_not_logged()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.private')
        ]);

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

        $this
            ->get('open/project/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_open_project_page_renders_correctly_private_logged_in()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'access' => config('epicollect.strings.project_access.private')
        ]);

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

        $this->actingAs($user)
            ->get('open/project/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_app_link_shown_home_page_private_project()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'app_link_visibility' => 'shown'
        ]);

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

        config(['epicollect.setup.system.app_link_enabled' => true]);
        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get('project/' . $project->slug)
            ->assertStatus(200);

        $response->assertSee('data-target="#modal-app-link"', false);
    }

    public function test_app_link_shown_home_page_private_public_project()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'app_link_visibility' => config('epicollect.strings.app_link_visibility.shown'),
            'access' => config('epicollect.strings.project_access.public')
        ]);

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


        config('epicollect.setup.system.app_link_enabled', true);
        $response = $this
            ->get('project/' . $project->slug)
            ->assertStatus(200);

        $response->assertSee('data-target="#modal-app-link"', false);
    }

    public function test_app_link_not_shown_home_page_private_public_project()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'app_link_visibility' => config('epicollect.strings.app_link_visibility.shown'),
            'access' => config('epicollect.strings.project_access.public')
        ]);

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


        config('epicollect.setup.system.app_link_enabled', false);
        $response = $this
            ->get('project/' . $project->slug)
            ->assertStatus(200);

        $response->assertDontSee('data-target="#modal-app-link"', false);
    }

    public function test_app_link_hidden_home_page_private_project()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'app_link_visibility' => config('epicollect.strings.app_link_visibility.hidden')
        ]);

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

        config(['epicollect.setup.system.app_link_enabled' => true]);
        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get('project/' . $project->slug)
            ->assertStatus(200);

        $response->assertDontSee('data-target="#modal-app-link"', false);
    }

    public function test_app_link_hidden_home_page_public_project()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create([
            'created_by' => $user->id,
            'app_link_visibility' => config('epicollect.strings.app_link_visibility.hidden'),
            'access' => config('epicollect.strings.project_access.public')
        ]);

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

        $response = $this
            ->get('project/' . $project->slug)
            ->assertStatus(200);

        $response->assertDontSee('data-target="#modal-app-link"', false);
    }

}
