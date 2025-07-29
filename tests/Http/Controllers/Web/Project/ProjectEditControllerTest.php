<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
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
            sleep(1);//to avoid race conditions
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
            sleep(1);//to avoid race conditions
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
            sleep(1);//to avoid race conditions
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
            sleep(1);//to avoid race conditions
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

    public function test_should_deny_access_to_unauthorized_user()
    {
        // Create another user who is not part of the project
        $unauthorizedUser = factory(User::class)->create();

        $response = $this->actingAs($unauthorizedUser)
            ->post("myprojects/" . $this->project->slug . "/settings/access", ["access" => "public"]);

        $response->assertStatus(404); // Should return 404 for unauthorized access
    }

    public function test_should_deny_access_to_unauthenticated_user()

    public function test_should_reject_invalid_access_value()
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/settings/access", ["access" => "invalid_access"]);

        $response->assertStatus(400); // Bad request for validation error
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("errors", $json);
    }

    public function test_should_reject_invalid_status_value()
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/settings/status", ["status" => "invalid_status"]);

        $response->assertStatus(400);
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("errors", $json);
    }

    public function test_should_reject_invalid_visibility_value()
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/settings/visibility", ["visibility" => "invalid_visibility"]);

        $response->assertStatus(400);
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("errors", $json);
    }

    public function test_should_reject_invalid_category_value()

    public function test_should_reject_missing_access_parameter()
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/settings/access", []);

        $response->assertStatus(400);
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("errors", $json);
    }

    public function test_should_reject_missing_status_parameter()
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/settings/status", []);

        $response->assertStatus(400);
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("errors", $json);
    }

    public function test_should_reject_missing_visibility_parameter()
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/settings/visibility", []);

        $response->assertStatus(400);
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("errors", $json);
    }

    public function test_should_reject_missing_category_parameter()

    public function test_should_reject_invalid_setting_action()

    public function test_should_return_404_for_nonexistent_project_access()
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/nonexistent-project/settings/access", ["access" => "public"]);

        $response->assertStatus(404);
    }

    public function test_should_return_404_for_nonexistent_project_details()

    public function test_should_reject_non_image_file_upload()
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        $file = UploadedFile::fake()->create("document.pdf", 1000, "application/pdf");

        $payload = [
            "_token" => csrf_token(),
            "small_description" => "This is a project small description",
            "description" => "This is a project long description",
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(["logo_url"]);
    }

    public function test_should_reject_empty_logo_file()

    public function test_should_accept_description_at_minimum_length()
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        $file = UploadedFile::fake()->image("logo.jpg", 300, 300)->size(500);

        $payload = [
            "_token" => csrf_token(),
            "small_description" => "This is a project small description",
            "description" => $this->generateStringOfLength(config("epicollect.limits.project.description.min")),
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHas("message", "ec5_123");
    }

    public function test_should_accept_description_at_maximum_length()
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        $file = UploadedFile::fake()->image("logo.jpg", 300, 300)->size(500);

        $payload = [
            "_token" => csrf_token(),
            "small_description" => "This is a project small description",
            "description" => $this->generateStringOfLength(config("epicollect.limits.project.description.max")),
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHas("message", "ec5_123");
    }

    public function test_should_accept_small_description_at_minimum_length()
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        $file = UploadedFile::fake()->image("logo.jpg", 300, 300)->size(500);

        $payload = [
            "_token" => csrf_token(),
            "small_description" => $this->generateStringOfLength(config("epicollect.limits.project.small_desc.min")),
            "description" => "This is a project long description",
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHas("message", "ec5_123");
    }

    public function test_should_accept_small_description_at_maximum_length()

    public function test_should_update_project_details_without_logo()

    public function test_should_allow_manager_to_update_project()
    {
        // Create a manager user
        $managerUser = factory(User::class)->create();
        $managerRole = config("epicollect.strings.project_roles.manager");
        factory(ProjectRole::class)->create([
            "user_id" => $managerUser->id,
            "project_id" => $this->project->id,
            "role" => $managerRole
        ]);

        $response = $this->actingAs($managerUser)
            ->post("myprojects/" . $this->project->slug . "/settings/access", ["access" => "public"]);

        $response->assertStatus(200);
    }

    public function test_should_deny_curator_from_changing_project_settings()

    public function test_should_handle_multiple_rapid_updates()
    {
        $accessValues = ["public", "private", "public"];
        $responses = [];

        foreach ($accessValues as $accessValue) {
            sleep(1); // Add small delay to avoid race conditions
            $responses[] = $this->actingAs($this->user)
                ->post("myprojects/" . $this->project->slug . "/settings/access", ["access" => $accessValue]);
        }

        // All requests should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Final state should match the last update
        $this->assertEquals("public", Project::where("id", $this->project->id)->value("access"));
    }

    public function test_should_maintain_data_consistency_after_updates()

    public function test_should_handle_special_characters_in_descriptions()

    public function test_should_accept_logo_at_exact_dimension_limits()

    public function test_should_handle_status_transitions_correctly()

    public function test_should_handle_database_transaction_rollback_on_failure()
    {
        // Test that database transaction is rolled back on failure
        $originalAccess = Project::where("id", $this->project->id)->value("access");
        
        // Mock a failure by trying to set an invalid value that would trigger an exception
        try {
            $response = $this->actingAs($this->user)
                ->post("myprojects/" . $this->project->slug . "/settings/access", ["access" => str_repeat("a", 1000)]);
                
            // Verify database state is unchanged after failure
            $currentAccess = Project::where("id", $this->project->id)->value("access");
            $this->assertEquals($originalAccess, $currentAccess);
        } catch (Exception $e) {
            // Expected to fail, verify rollback occurred
            $currentAccess = Project::where("id", $this->project->id)->value("access");
            $this->assertEquals($originalAccess, $currentAccess);
        }
    }

    public function test_should_handle_corrupted_image_file()
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        // Create a file that claims to be an image but has corrupted data
        $file = UploadedFile::fake()->createWithContent(
            "corrupted.jpg",
            "this is not image data"
        );

        $payload = [
            "_token" => csrf_token(),
            "small_description" => "This is a project small description",
            "description" => "This is a project long description",
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_should_handle_unicode_in_project_descriptions()
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        $file = UploadedFile::fake()->image("logo.jpg", 300, 300)->size(500);
        $unicodeText = "测试中文 français русский العربية 日本語 한국어";

        $payload = [
            "_token" => csrf_token(),
            "small_description" => "Unicode test: " . substr($unicodeText, 0, 20),
            "description" => "Full unicode test: " . $unicodeText,
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHas("message", "ec5_123");

        // Verify unicode is properly stored
        $projectAfterUpdate = Project::find($this->project->id);
        $this->assertEquals($payload["description"], $projectAfterUpdate->description);
        $this->assertEquals($payload["small_description"], $projectAfterUpdate->small_description);
    }
    {
        $statusTransitions = [
            ["from" => "active", "to" => "locked"],
            ["from" => "locked", "to" => "trashed"],
            ["from" => "trashed", "to" => "active"]
        ];

        foreach ($statusTransitions as $transition) {
            sleep(1); // Avoid race conditions
            
            // Set initial status
            Project::where("id", $this->project->id)->update(["status" => $transition["from"]]);
            
            // Update to new status
            $response = $this->actingAs($this->user)
                ->post("myprojects/" . $this->project->slug . "/settings/status", ["status" => $transition["to"]]);

            $response->assertStatus(200);
            
            // Verify status was updated
            $currentStatus = Project::where("id", $this->project->id)->value("status");
            $this->assertEquals($transition["to"], $currentStatus);
        }
    }
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        // Create image at exact maximum dimensions
        $file = UploadedFile::fake()->image(
            "logo.jpg",
            config("epicollect.limits.project.logo.width"),
            config("epicollect.limits.project.logo.height")
        )->size(config("epicollect.limits.project.logo.size") - 1); // Just under size limit

        $payload = [
            "_token" => csrf_token(),
            "small_description" => "This is a project small description",
            "description" => "This is a project long description",
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHas("message", "ec5_123");
    }
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        $file = UploadedFile::fake()->image("logo.jpg", 300, 300)->size(500);
        $specialChars = "Test with émojis 🎉 and spécial chars & symbols <script>alert("test")</script>";

        $payload = [
            "_token" => csrf_token(),
            "small_description" => "Test with special chars: & < > "",
            "description" => $specialChars,
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHas("message", "ec5_123");

        // Verify special characters are properly stored
        $projectAfterUpdate = Project::find($this->project->id);
        $this->assertEquals($payload["description"], $projectAfterUpdate->description);
    }
    {
        $testValues = [
            ["access" => "private"],
            ["status" => "locked"],
            ["visibility" => "hidden"],
            ["category" => "general"]
        ];

        foreach ($testValues as $data) {
            sleep(1); // Avoid race conditions
            $key = array_keys($data)[0];
            $value = $data[$key];
            
            $response = $this->actingAs($this->user)
                ->post("myprojects/" . $this->project->slug . "/settings/" . $key, $data);

            $response->assertStatus(200);

            // Verify database consistency
            $projectStructure = ProjectStructure::where("project_id", $this->project->id)->first();
            $projectDefinition = json_decode($projectStructure->project_definition, true);
            $projectExtra = json_decode($projectStructure->project_extra, true);

            $this->assertEquals($value, $projectDefinition["project"][$key]);
            $this->assertEquals($value, $projectExtra["project"]["details"][$key]);
        }
    }
    {
        // Create a curator user (assuming curators have limited permissions)
        $curatorUser = factory(User::class)->create();
        $curatorRole = config("epicollect.strings.project_roles.curator");
        factory(ProjectRole::class)->create([
            "user_id" => $curatorUser->id,
            "project_id" => $this->project->id,
            "role" => $curatorRole
        ]);

        $response = $this->actingAs($curatorUser)
            ->post("myprojects/" . $this->project->slug . "/settings/access", ["access" => "public"]);

        $response->assertStatus(404); // Should return 404 for insufficient permissions
    }
    {
        $payload = [
            "_token" => csrf_token(),
            "small_description" => "Updated small description",
            "description" => "Updated long description about the project",
            "data" => []
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHas("message", "ec5_123");

        // Assert that descriptions were updated
        $projectAfterUpdate = Project::find($this->project->id);
        $this->assertEquals($projectAfterUpdate->small_description, $payload["small_description"]);
        $this->assertEquals($projectAfterUpdate->description, $payload["description"]);
    }
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        $file = UploadedFile::fake()->image("logo.jpg", 300, 300)->size(500);

        $payload = [
            "_token" => csrf_token(),
            "small_description" => $this->generateStringOfLength(config("epicollect.limits.project.small_desc.max")),
            "description" => "This is a project long description",
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHas("message", "ec5_123");
    }
    {
        Storage::fake("project_thumb");
        Storage::fake("project_mobile_logo");

        $file = UploadedFile::fake()->create("empty.jpg", 0);

        $payload = [
            "_token" => csrf_token(),
            "small_description" => "This is a project small description",
            "description" => "This is a project long description",
            "data" => [],
            "logo_url" => $file
        ];

        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/details", $payload);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(["logo_url"]);
    }
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/nonexistent-project/details", [
                "_token" => csrf_token(),
                "small_description" => "Test description",
                "description" => "Test long description"
            ]);

        $response->assertStatus(404);
    }
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/settings/invalid_action", ["invalid_action" => "test"]);

        $response->assertStatus(400);
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("errors", $json);
        $this->assertContains("ec5_29", $json["errors"]);
    }
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/settings/category", []);

        $response->assertStatus(400);
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("errors", $json);
    }
    {
        $response = $this->actingAs($this->user)
            ->post("myprojects/" . $this->project->slug . "/settings/category", ["category" => "invalid_category"]);

        $response->assertStatus(400);
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey("errors", $json);
    }
    {
        $response = $this->post("myprojects/" . $this->project->slug . "/settings/access", ["access" => "public"]);

        $response->assertStatus(302); // Redirect to login
    }
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
