<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Libraries\Utilities\Strings;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStat;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Eloquent\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ProjectControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    public function test_project_home_page_renders_correctly()
    {
        //create mock user
        $user = factory(User::class)->create();

        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        $role = Config::get('ec5Strings.project_roles.creator');
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
        $role = Config::get('ec5Strings.project_roles.creator');
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        /*
        set up project stats and project structures (to make R&A middleware work, to be removed)
        because they are using a repository with joins
        */
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
            ->get('myprojects/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_project_details_page_renders_correctly_with_server_role_superadmin()
    {
        //create mock user
        $user = factory(User::class)->create(
            ['server_role' => Config::get('ec5Strings.server_roles.superadmin')]
        );

        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        $role = Config::get('ec5Strings.project_roles.creator');
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        /*
        set up project stats and project structures (to make R&A middleware work, to be removed)
        because they are using a repository with joins
        */
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
            ->get('myprojects/' . $project->slug)
            ->assertStatus(200);
    }

    public function test_project_details_page_renders_correctly_with_server_role_admin()
    {
        //create mock user
        $user = factory(User::class)->create(
            ['server_role' => Config::get('ec5Strings.server_roles.admin')]
        );

        //create a fake project with that user
        $project = factory(Project::class)->create(['created_by' => $user->id]);

        //assign the user to that project with the CREATOR role
        $role = Config::get('ec5Strings.project_roles.creator');
        $projectRole = factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => $role
        ]);

        /*
        set up project stats and project structures (to make R&A middleware work, to be removed)
        because they are using a repository with joins
        */
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
        $role = Config::get('ec5Strings.project_roles.creator');
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
        $role = Config::get('ec5Strings.project_roles.creator');
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
            ->get(Route('formbuilder', ['project_slug' => $project->slug]))
            ->assertStatus(200);

        $this->assertEquals($project->name, $response->original['projectName']); // Check the data passed to the view
        $this->assertEquals('project.formbuilder', $response->original->getName());
        // Assert that the view has the expected data, in this case, 'projectName' set to $this->requestedProject->name
        $response->assertViewHas('projectName', $project->name);

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
                'role' => config('ec5Strings.project_roles.manager')
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
            ->get(Route('formbuilder', ['project_slug' => $project->slug]))
            ->assertStatus(200);

        $this->assertEquals($project->name, $response->original['projectName']); // Check the data passed to the view
        $this->assertEquals('project.formbuilder', $response->original->getName());
        // Assert that the view has the expected data, in this case, 'projectName' set to $this->requestedProject->name
        $response->assertViewHas('projectName', $project->name);

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
                'role' => config('ec5Strings.project_roles.curator')
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
        $this->assertInternalType('array', $errorsArray);
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
                'role' => config('ec5Strings.project_roles.collector')
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
        $this->assertInternalType('array', $errorsArray);
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
                'role' => config('ec5Strings.project_roles.viewer')
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
        $this->assertInternalType('array', $errorsArray);
        $this->assertEquals('ec5_91', $errorsArray[0]);
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
                'role' => config('ec5Strings.project_roles.creator')
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
            ->get('myprojects/' . $project->slug . '/download-project-definition')
            ->assertStatus(200);
    }

}