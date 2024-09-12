<?php

namespace Tests\Http\Controllers\Web\Project;

use Auth;
use ec5\Libraries\Utilities\Common;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

class ProjectCloneControllerTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    public const DRIVER = 'web';
    private $faker;
    private $user;
    private $project;
    private $projectDefinition;
    private $projectExtra;
    private $projectMapping;

    public function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();

        //create fake user for testing
        $user = factory(User::class)->create();
        //create a project with custom project definition
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private')
            ]
        );
        //add role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //create basic project definition
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'])
            ]
        );
        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );

        //upload the project definition via the formbuilder controller
        // Convert data array to JSON
        $jsonData = json_encode($projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = $this->actingAs($user)
            ->call(
                'POST',
                'api/internal/formbuilder/' . $project->slug,
                [],
                [],
                [],
                [],
                $base64EncodedData
            );

        $response->assertStatus(200);

        $projectStructure = ProjectStructure::where('project_id', $project->id)->first();
        $projectExtra = json_decode($projectStructure->project_extra, true);
        $projectMapping = json_decode($projectStructure->project_mapping, true);

        $this->user = $user;
        $this->project = $project;
        $this->projectDefinition = $projectDefinition;
        $this->projectExtra = $projectExtra;
        $this->projectMapping = $projectMapping;
    }


    public function test_clone_page_renders_correctly()
    {
        Auth::logout();
        $this
            ->actingAs($this->user, self::DRIVER)
            ->get('myprojects/' . $this->project->slug . '/clone')
            ->assertStatus(200);
    }

    public function test_clone_page_redirect_if_not_logged_in()
    {
        Auth::logout();
        $this->get('myprojects/' . $this->project->slug . '/clone')
            ->assertStatus(302)
            ->assertRedirect(Route('login'));
    }

    public function test_project_is_cloned_without_users()
    {
        //clone project
        $projectName = Generators::projectRef();
        $response = $this
            ->actingAs($this->user, self::DRIVER)
            ->post(
                'myprojects/' . $this->project->slug . '/clone',
                [
                    '_token' => csrf_token(),
                    'name' => $projectName
                ]
            )
            ->assertStatus(302);
        $response->assertRedirect('/myprojects');
        $response->assertSessionHas('message', 'ec5_200');

        //assert the project was cloned
        $this->assertCount(1, Project::where('name', $projectName)->get());
        //get the cloned project
        $clonedProject = Project::where('name', $projectName)->first();

        //assert other tables
        $this->assertCount(1, ProjectStructure::where('project_id', $clonedProject->id)->get());
        $this->assertCount(1, ProjectStats::where('project_id', $clonedProject->id)->get());

        //assert only the creator role was copied
        $this->assertCount(1, ProjectRole::where('project_id', $clonedProject->id)->get());
        $this->assertEquals(
            config('epicollect.strings.project_roles.creator'),
            ProjectRole::where('project_id', $clonedProject->id)->value('role')
        );

        $clonedProjectStructures = ProjectStructure::where('project_id', $clonedProject->id)->first();

        $clonedProjectDefinition = json_decode($clonedProjectStructures->project_definition, true);

        //assert ref was changed
        $this->assertEquals($clonedProject->ref, $clonedProjectDefinition['project']['ref']);


        //reverse changes done by clone controller for the assertions below
        $clonedProjectDefinition['project']['name'] = $this->project->name;
        $clonedProjectDefinition['project']['slug'] = $this->project->slug;
        $originalProjectDefinition = Common::replaceRefInStructure(
            $clonedProject->ref,
            $this->project->ref,
            $clonedProjectDefinition
        );
        $this->assertEquals($originalProjectDefinition, $this->projectDefinition['data']);

        $clonedProjectExtra = json_decode($clonedProjectStructures->project_extra, true);
        //assert ref was changed
        $this->assertEquals($clonedProject->ref, $clonedProjectExtra['project']['details']['ref']);
        //reverse changes done by clone controller for the assertions below
        $clonedProjectExtra['project']['details']['name'] = $this->project->name;
        $clonedProjectExtra['project']['details']['slug'] = $this->project->slug;
        $originalProjectExtra = Common::replaceRefInStructure(
            $clonedProject->ref,
            $this->project->ref,
            $clonedProjectExtra
        );
        $this->assertEquals($originalProjectExtra, $this->projectExtra);

        //reverse changes done by clone controller
        $clonedProjectMapping = json_decode($clonedProjectStructures->project_mapping, true);
        $originalProjectMapping = Common::replaceRefInStructure(
            $clonedProject->ref,
            $this->project->ref,
            $clonedProjectMapping
        );
        $this->assertEquals($originalProjectMapping, $this->projectMapping);

        //assert avatar is created
        $this->assertAvatarCreated($clonedProject);

    }

    public function test_project_is_cloned_with_users()
    {
        //add users to the project with random roles
        $roles = [
            config('epicollect.strings.project_roles.manager'),
            config('epicollect.strings.project_roles.curator'),
            config('epicollect.strings.project_roles.collector'),
            config('epicollect.strings.project_roles.viewer')
        ];

        $usersCount = rand(1, 10);
        $projectMembers = [];
        for ($i = 0; $i < $usersCount; $i++) {
            $user = factory(User::class)->create();
            $role = $this->faker->randomElement($roles);
            factory(ProjectRole::class)->create(
                [
                    'user_id' => $user->id,
                    'project_id' => $this->project->id,
                    'role' => $role
                ]
            );
            $projectMembers[] = [
                'user_id' => $user->id,
                'role' => $role
            ];
        }

        //clone project
        $projectName = Generators::projectRef();
        $response = $this
            ->actingAs($this->user, self::DRIVER)
            ->post(
                'myprojects/' . $this->project->slug . '/clone',
                [
                    '_token' => csrf_token(),
                    'name' => $projectName,
                    'clone-users' => 'y'
                ]
            )
            ->assertStatus(302);
        $response->assertRedirect('/myprojects');
        $response->assertSessionHas('message', 'ec5_200');

        //assert the project was cloned
        $this->assertCount(1, Project::where('name', $projectName)->get());
        //get the cloned project
        $clonedProject = Project::where('name', $projectName)->first();

        //assert other tables
        $this->assertCount(1, ProjectStructure::where('project_id', $clonedProject->id)->get());
        $this->assertCount(1, ProjectStats::where('project_id', $clonedProject->id)->get());

        //assert all the roles were copied
        $this->assertCount(1 + $usersCount, ProjectRole::where('project_id', $clonedProject->id)->get());
        foreach ($projectMembers as $projectMember) {
            $this->assertCount(1, ProjectRole::where(
                'project_id',
                $clonedProject->id
            )
                ->where('user_id', $projectMember['user_id'])
                ->where('role', $projectMember['role'])
                ->get());
        }

        $clonedProjectStructures = ProjectStructure::where('project_id', $clonedProject->id)->first();

        //reverse changes done by clone controller
        $clonedProjectDefinition = json_decode($clonedProjectStructures->project_definition, true);
        $clonedProjectDefinition['project']['name'] = $this->project->name;
        $clonedProjectDefinition['project']['slug'] = $this->project->slug;
        $originalProjectDefinition = Common::replaceRefInStructure(
            $clonedProject->ref,
            $this->project->ref,
            $clonedProjectDefinition
        );
        $this->assertEquals($originalProjectDefinition, $this->projectDefinition['data']);

        //reverse changes done by clone controller
        $clonedProjectExtra = json_decode($clonedProjectStructures->project_extra, true);
        $clonedProjectExtra['project']['details']['name'] = $this->project->name;
        $clonedProjectExtra['project']['details']['slug'] = $this->project->slug;
        $originalProjectExtra = Common::replaceRefInStructure(
            $clonedProject->ref,
            $this->project->ref,
            $clonedProjectExtra
        );
        $this->assertEquals($originalProjectExtra, $this->projectExtra);

        //reverse changes done by clone controller
        $clonedProjectMapping = json_decode($clonedProjectStructures->project_mapping, true);
        $originalProjectMapping = Common::replaceRefInStructure(
            $clonedProject->ref,
            $this->project->ref,
            $clonedProjectMapping
        );
        $this->assertEquals($originalProjectMapping, $this->projectMapping);

        //assert avatar is created
        $this->assertAvatarCreated($clonedProject);
    }
}
