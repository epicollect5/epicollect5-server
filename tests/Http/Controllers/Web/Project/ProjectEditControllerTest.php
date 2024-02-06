<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class ProjectEditControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    private $user;
    private $project;

    public function setUp()
    {
        parent::setUp();

        //create mock user
        $user = factory(User::class)->create();
        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        //create a fake project with that user
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => $projectDefinition['data']['project']['name'],
                'slug' => $projectDefinition['data']['project']['slug']
            ]
        );
        //assign the user to that project with the CREATOR role
        $role = config('epicollect.strings.project_roles.creator');
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
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'], JSON_UNESCAPED_SLASHES)
            ]
        );

        $this->user = $user;
        $this->project = $project;
    }

    public function test_request_should_update_access()
    {
        $accessValues = array_keys(config('epicollect.strings.project_access'));
        foreach ($accessValues as $accessValue) {
            $response = [];
            try {
                $response[] = $this->actingAs($this->user)->post('myprojects/' . $this->project->slug . '/settings/access', ['access' => $accessValue]);
                $response[0]->assertStatus(200);

                $json = json_decode($response[0]->getContent(), true);
                $this->assertEquals($json['data']['access'], $accessValue);
                $this->assertEquals($accessValue, Project::where('id', $this->project->id)->value('access'));

                $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
                $projectDefinition = json_decode($projectStructure->project_definition, true);
                $projectExtra = json_decode($projectStructure->project_extra, true);

                //assert project definition
                $this->assertEquals($accessValue, $projectDefinition['project']['access']);
                //assert project extra
                $this->assertEquals($accessValue, $projectExtra['project']['details']['access']);

            } catch (Exception $e) {
                $this->logTestError($e, $response);
            }
        }
    }

    public function test_request_should_update_status()
    {
        $statusValues = ['active', 'trashed', 'locked'];
        foreach ($statusValues as $statusValue) {
            $response = [];
            try {
                $response[] = $this->actingAs($this->user)->post('myprojects/' . $this->project->slug . '/settings/status',
                    ['status' => $statusValue]
                );
                $response[0]->assertStatus(200);

                $json = json_decode($response[0]->getContent(), true);
                $this->assertEquals($json['data']['status'], $statusValue);
                $this->assertEquals($statusValue, Project::where('id', $this->project->id)->value('status'));

                $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
                $projectDefinition = json_decode($projectStructure->project_definition, true);
                $projectExtra = json_decode($projectStructure->project_extra, true);

                //assert project definition
                $this->assertEquals($statusValue, $projectDefinition['project']['status']);
                //assert project extra
                $this->assertEquals($statusValue, $projectExtra['project']['details']['status']);

            } catch (Exception $e) {
                $this->logTestError($e, $response);
            }
        }
    }

    public function test_request_should_update_visibility()
    {
        $visibilityValues = array_keys(config('epicollect.strings.project_visibility'));
        foreach ($visibilityValues as $visibilityValue) {
            $response = [];
            try {
                $response[] = $this->actingAs($this->user)
                    ->post('myprojects/' . $this->project->slug . '/settings/visibility',
                        ['visibility' => $visibilityValue]
                    );
                $response[0]->assertStatus(200);

                $json = json_decode($response[0]->getContent(), true);
                $this->assertEquals($json['data']['visibility'], $visibilityValue);
                $this->assertEquals($visibilityValue, Project::where('id', $this->project->id)
                    ->value('visibility'));

                $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
                $projectDefinition = json_decode($projectStructure->project_definition, true);
                $projectExtra = json_decode($projectStructure->project_extra, true);

                //assert project definition
                $this->assertEquals($visibilityValue, $projectDefinition['project']['visibility']);
                //assert project extra
                $this->assertEquals($visibilityValue, $projectExtra['project']['details']['visibility']);
            } catch (Exception $e) {
                $this->logTestError($e, $response);
            }
        }
    }

    public function test_request_should_update_category()
    {
        $categories = array_keys(config('epicollect.strings.project_categories'));
        foreach ($categories as $category) {
            $response = [];
            try {
                $response[] = $this->actingAs($this->user)
                    ->post('myprojects/' . $this->project->slug . '/settings/category',
                        ['category' => $category]
                    );
                $response[0]->assertStatus(200);

                $json = json_decode($response[0]->getContent(), true);
                $this->assertEquals($json['data']['category'], $category);
                $this->assertEquals($category, Project::where('id', $this->project->id)->value('category'));

                $projectStructure = ProjectStructure::where('project_id', $this->project->id)->first();
                $projectDefinition = json_decode($projectStructure->project_definition, true);
                $projectExtra = json_decode($projectStructure->project_extra, true);

                //assert project definition
                $this->assertEquals($category, $projectDefinition['project']['category']);
                //assert project extra
                $this->assertEquals($category, $projectExtra['project']['details']['category']);
            } catch (Exception $e) {
                $this->logTestError($e, $response);
            }
        }
    }
}