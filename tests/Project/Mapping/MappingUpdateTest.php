<?php

namespace Tests\Project;

use Tests\TestCase;
use ec5\Http\Validation\Project\Mapping\RuleMappingUpdate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ec5\Models\Projects\Project;
use ec5\Models\Projects\ProjectDefinition;
use ec5\Models\Projects\ProjectExtra;
use ec5\Models\Projects\ProjectMapping;
use ec5\Models\Projects\ProjectStats;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as SearchProjectRepository;
use ec5\Models\Users\User;
use Illuminate\Support\Str;
use ec5\Libraries\Utilities\Generators;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as ProjectUpdate;


class MappingUpdateTest extends TestCase
{
    //to reset database after tests
    use DatabaseTransactions;

    protected $projectExtra;
    protected $projectMapping;
    protected $validator;
    protected $user;
    protected $request;

    public function setUp()
    {
        parent::setUp();
        $this->validator = new RuleMappingUpdate();
        //to have a user logged in as superadmin
        $this->user = User::find(1);
        $this->request = [
            'name' => 'EC5 Mapping Update Unit Tests',
            'form_name' => 'Form One',
            'small_description' => 'Test mapping update',
            'access' => 'public'
        ];
    }



    //imp: can I not load a base test project i created for unit tests only?
    //otherwise I need a factory class that generate random projects
    //good idea actually, without jumps though
    //then the database is rolled back anyway (to remove the mappings, project remains)
    public function testValidUpdate()
    {
        //create a test project in the DB so default mapping gets set
        $slug = Str::slug($this->request['name'], '-');
        $response = $this->actingAs($this->user)->post('myprojects/create',  $this->request);
        $response->assertStatus(302)
            ->assertRedirect('myprojects/' . $slug)
            ->assertSessionHas(['projectCreated' => true]);

        //grab that project from the db (by slug)
        $projectDefinition = new ProjectDefinition();
        $projectExtra = new ProjectExtra();
        $projectMapping = new ProjectMapping();
        $projectStats = new ProjectStats();
        $projectUpdate = new ProjectUpdate();
        $currentProject = new Project(
            $projectDefinition,
            $projectExtra,
            $projectMapping,
            $projectStats
        );

        $searchProjectLegacy = new SearchProjectRepository();
        // Retrieve project with updated stats (legacy way, R&A fiasco)
        $project = $searchProjectLegacy->find($slug);
        // Refresh the main Project model
        $currentProject->init($project);

        $projectDefinition = $currentProject->getProjectDefinition()->getData();
        $formRef = $projectDefinition['project']['forms'][0]['ref'];
        $projectDefinition['project']['forms'][0]['inputs'][] = Generators::input($formRef);
        $projectDefinition['project']['forms'][0]['inputs'][] = Generators::input($formRef);

        dd($projectDefinition);



        $currentProject->addProjectDefinition($projectDefinition);
        // Update Project Mappings
        $currentProject->updateProjectMappings();
        $projectUpdate->updateProjectStructure($currentProject, true);

        //project is currently empty
        // 1 - add questions (factory?)
        // 2 -update projectMapping
        //save to db



        //call the create endpoint?

        //create a valid custom map first! (EC5_AUTO is not editable)


        $updateRequest = [
            'action' => 'update',
            'map_index' => 1,
            'mapping' => []
        ];

        $this->validator->validate($updateRequest);
    }
}
