<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Storage;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;
use Throwable;

class ProjectEditControllerTest extends TestCase
{
    use DatabaseTransactions;

    public const string DRIVER = 'web';

    private User $user;
    private Project $project;
    private Generator $faker;

    public function setUp(): void
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
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'], JSON_UNESCAPED_SLASHES)
            ]
        );

        $this->user = $user;
        $this->project = $project;
        $this->faker = Faker::create();

    }

    public function test_should_update_access()
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

            } catch (Throwable $e) {
                $this->logTestError($e, $response);
            }
        }
    }

    public function test_should_update_status()
    {
        $statusValues = ['active', 'trashed', 'locked'];
        foreach ($statusValues as $statusValue) {
            $response = [];
            try {
                $response[] = $this->actingAs($this->user)->post(
                    'myprojects/' . $this->project->slug . '/settings/status',
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

            } catch (Throwable $e) {
                $this->logTestError($e, $response);
            }
        }
    }

    public function test_should_update_visibility()
    {
        $visibilityValues = array_keys(config('epicollect.strings.project_visibility'));
        foreach ($visibilityValues as $visibilityValue) {
            $response = [];
            try {
                $response[] = $this->actingAs($this->user)
                    ->post(
                        'myprojects/' . $this->project->slug . '/settings/visibility',
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
            } catch (Throwable $e) {
                $this->logTestError($e, $response);
            }
        }
    }

    public function test_should_update_category()
    {
        $categories = array_keys(config('epicollect.strings.project_categories'));
        foreach ($categories as $category) {
            $response = [];
            try {
                $response[] = $this->actingAs($this->user)
                    ->post(
                        'myprojects/' . $this->project->slug . '/settings/category',
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
            } catch (Throwable $e) {
                $this->logTestError($e, $response);
            }
        }
    }



    public function test_should_detect_file_size_too_big()
    {
        // Fake the local storage for project_thumb and project_mobile_logo
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Create a fake image file (100x100 pixels, 200 KB)
        $file = UploadedFile::fake()->image(
            'logo.jpg',
            1024,
            1024
        )
            ->size(config('epicollect.limits.project.logo.size') + 1);

        $payload = [
            '_token' => csrf_token(),
            'small_description' => 'This is a project small description',
            'description' => 'This is a project long description about the project content and data',
            'data' => [],
            'logo_url' => $file
        ];

        $response[] = $this->actingAs($this->user)
            ->post(
                'myprojects/' . $this->project->slug . '/details',
                $payload
            );

        $response[0]->assertStatus(302);

        // Assert that the session contains the specific error messages
        $response[0]->assertSessionHasErrors([
            'logo_url' => "Logo file size too large. Max file size is 5M"

        ]);
    }

    public function test_should_detect_logo_resolution_too_high_both_width_and_height()
    {
        // Fake the local storage for project_thumb and project_mobile_logo
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Create a fake image file (100x100 pixels, 200 KB)
        $file = UploadedFile::fake()->image('logo.jpg', 5000, 5000)->size(200);

        $payload = [
            '_token' => csrf_token(),
            'small_description' => 'This is a project small description',
            'description' => 'This is a project long description about the project content and data',
            'data' => [],
            'logo_url' => $file
        ];

        $response[] = $this->actingAs($this->user)
            ->post(
                'myprojects/' . $this->project->slug . '/details',
                $payload
            );

        $response[0]->assertStatus(302);

        // Assert that the session contains the specific error messages (with both dimensions)
        $response[0]->assertSessionHasErrors([
            'logo_width' => 'ec5_332'
        ]);
        $response[0]->assertSessionHasErrors([
            'logo_height' => 'ec5_332'
        ]);

        // Assert that the image was stored in the correct directories
        //Storage::disk('project_thumb')->assertExists('test-project/logo.jpg');
        //Storage::disk('project_mobile_logo')->assertExists('test-project/logo.jpg');
    }

    public function test_should_detect_logo_resolution_too_high_width()
    {
        // Fake the local storage for project_thumb and project_mobile_logo
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Create a fake image file (100x100 pixels, 200 KB)
        $file = UploadedFile::fake()->image('logo.jpg', 5000, 100)->size(200);

        $payload = [
            '_token' => csrf_token(),
            'small_description' => 'This is a project small description',
            'description' => 'This is a project long description about the project content and data',
            'data' => [],
            'logo_url' => $file
        ];

        $response[] = $this->actingAs($this->user)
            ->post(
                'myprojects/' . $this->project->slug . '/details',
                $payload
            );

        $response[0]->assertStatus(302);

        // Assert that the session contains the specific error messages
        $response[0]->assertSessionHasErrors([
            'logo_width' => 'ec5_332'
        ]);

        // Assert that the image was stored in the correct directories
        //Storage::disk('project_thumb')->assertExists('test-project/logo.jpg');
        //Storage::disk('project_mobile_logo')->assertExists('test-project/logo.jpg');
    }

    public function test_should_detect_logo_resolution_too_high_height()
    {
        // Fake the local storage for project_thumb and project_mobile_logo
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Create a fake image file (100x100 pixels, 200 KB)
        $file = UploadedFile::fake()->image('logo.jpg', 300, 5000)->size(200);

        $payload = [
            '_token' => csrf_token(),
            'small_description' => 'This is a project small description',
            'description' => 'This is a project long description about the project content and data',
            'data' => [],
            'logo_url' => $file
        ];

        $response[] = $this->actingAs($this->user)
            ->post(
                'myprojects/' . $this->project->slug . '/details',
                $payload
            );

        $response[0]->assertStatus(302);

        // Assert that the session contains the specific error messages
        $response[0]->assertSessionHasErrors([
            'logo_height' => 'ec5_332'
        ]);
    }

    public function test_should_detect_description_too_long()
    {
        // Fake the local storage for project_thumb and project_mobile_logo
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Create a fake image file (100x100 pixels, 200 KB)
        $file = UploadedFile::fake()->image('logo.jpg', 300, 300)->size(500);

        $payload = [
            '_token' => csrf_token(),
            'small_description' => 'This is a project small description',
            'description' => $this->generateStringOfLength(config('epicollect.limits.project.description.max') + 1),
            'data' => [],
            'logo_url' => $file
        ];

        $response[] = $this->actingAs($this->user)
            ->post(
                'myprojects/' . $this->project->slug . '/details',
                $payload
            );

        $response[0]->assertStatus(302);

        // Assert that the session contains the specific error messages
        $response[0]->assertSessionHasErrors([
            'description' => 'Project description must be between 3 to 3000 chars long'
        ]);
    }

    public function test_should_detect_description_too_short()
    {
        // Fake the local storage for project_thumb and project_mobile_logo
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Create a fake image file (100x100 pixels, 200 KB)
        $file = UploadedFile::fake()->image('logo.jpg', 300, 300)->size(500);

        $payload = [
            '_token' => csrf_token(),
            'small_description' => 'This is a project small description',
            'description' => $this->generateStringOfLength(config('epicollect.limits.project.description.min') - 1),
            'data' => [],
            'logo_url' => $file
        ];

        $response[] = $this->actingAs($this->user)
            ->post(
                'myprojects/' . $this->project->slug . '/details',
                $payload
            );

        $response[0]->assertStatus(302);

        // Assert that the session contains the specific error messages
        $response[0]->assertSessionHasErrors([
            'description' => 'Project description must be between 3 to 3000 chars long'
        ]);
    }

    public function test_should_update_project_details()
    {
        // Fake the local storage for project_thumb and project_mobile_logo
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Create a fake image file within the limits
        $file = UploadedFile::fake()->image(
            'logo.jpg',
            config('epicollect.limits.project.logo.width'),
            config('epicollect.limits.project.logo.height')
        )
            ->size(config('epicollect.limits.project.logo.size'));

        $payload = [
            '_token' => csrf_token(),
            'small_description' => 'This is a project small description',
            'description' => 'This is a project long description about the project content and data',
            'data' => [],
            'logo_url' => $file
        ];

        $response[] = $this->actingAs($this->user)
            ->post(
                'myprojects/' . $this->project->slug . '/details',
                $payload
            );

        $response[0]->assertStatus(302);
        // Assert that the session contains the specific success messages
        $response[0]->assertSessionHas('message', 'ec5_123');

        // Assert that the image was stored in the correct directories
        Storage::disk('project_thumb')->assertExists($this->project->ref . '/logo.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->project->ref . '/logo.jpg');

        //assert small desc and description were updated
        $projectAfterUpdate = Project::find($this->project->id);
        $this->assertEquals($projectAfterUpdate->small_description, $payload['small_description']);
        $this->assertEquals($projectAfterUpdate->description, $payload['description']);
        $this->assertNotEmpty($projectAfterUpdate->logo_url);
    }

    public function test_should_detect_small_description_too_long()
    {
        // Fake the local storage for project_thumb and project_mobile_logo
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Create a fake image file within the limits
        $file = UploadedFile::fake()->image(
            'logo.jpg',
            config('epicollect.limits.project.logo.width'),
            config('epicollect.limits.project.logo.height')
        )
            ->size(config('epicollect.limits.project.logo.size'));

        $payload = [
            '_token' => csrf_token(),
            'small_description' => $this->generateStringOfLength(config('epicollect.limits.project.small_desc.max') + 1),
            'description' => 'This is a project long description about the project content and data',
            'data' => [],
            'logo_url' => $file
        ];

        $response[] = $this->actingAs($this->user)
            ->post(
                'myprojects/' . $this->project->slug . '/details',
                $payload
            );

        $response[0]->assertStatus(302);

        // Assert that the session contains the specific error messages
        $response[0]->assertSessionHasErrors([
            'small_description' => 'Project small description must be between 15 to 100 chars long'
        ]);
    }

    public function test_should_detect_small_description_too_short()
    {
        // Fake the local storage for project_thumb and project_mobile_logo
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Create a fake image file within the limits
        $file = UploadedFile::fake()->image(
            'logo.jpg',
            config('epicollect.limits.project.logo.width'),
            config('epicollect.limits.project.logo.height')
        )
            ->size(config('epicollect.limits.project.logo.size'));

        $payload = [
            '_token' => csrf_token(),
            'small_description' => $this->generateStringOfLength(config('epicollect.limits.project.small_desc.min') - 1),
            'description' => 'This is a project long description about the project content and data',
            'data' => [],
            'logo_url' => $file
        ];

        $response[] = $this->actingAs($this->user)
            ->post(
                'myprojects/' . $this->project->slug . '/details',
                $payload
            );

        $response[0]->assertStatus(302);

        // Assert that the session contains the specific error messages
        $response[0]->assertSessionHasErrors([
            'small_description' => 'Project small description must be between 15 to 100 chars long'
        ]);
    }

}
