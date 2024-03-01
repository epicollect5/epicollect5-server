<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;


class ManageUsersControllerTest extends TestCase
{
    use DatabaseTransactions;

    private $faker;
    private $user;
    private $project;
    private $projectDefinition;

    public function setUp()
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

        $this->user = $user;
        $this->project = $project;

    }

    public function test_manage_users_redirect_if_not_logged_in()
    {
        $response = [];
        try {
            $response[] = $this->get('myprojects/' . $this->project->slug . '/manage-users');
            $response[0]->assertStatus(302);
            $response[0]->assertRedirect(Route('login'));
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }
    }


    public function test_manage_users_page_renders_correctly()
    {
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->get('myprojects/' . $this->project->slug . '/manage-users');
            $response[0]->assertStatus(200);
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_user_is_added_as_manager_to_project()
    {
        $randomRole = config('epicollect.strings.project_roles.manager');
        $randomEmail = $this->faker->unique()->safeEmail();
        $payload = [
            'email' => $randomEmail,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );
            $response[0]->assertStatus(200);

            //assert role is added
            $this->assertEquals(1, User::where('email', $randomEmail)->count());
            $newUserId = User::where('email', $randomEmail)->first()->id;
            $this->assertEquals(
                1,
                ProjectRole::where('user_id', $newUserId)->where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }

    }

    public function test_user_cannot_be_added_twice_to_project()
    {
        //create a manager user and add it to the project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        //Try to add the same user. It will just be updated
        $randomRole = $this->faker->randomElement(config('epicollect.permissions.projects.roles.creator'));
        $payload = [
            'email' => $manager->email,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            //user added, but it is just updated
            $response[0]->assertStatus(200);

            //assert the role is only updated
            $this->assertEquals(1, User::where('email', $manager->email)->count());
            $newUserId = User::where('email', $manager->email)->first()->id;
            $this->assertEquals(
                1,
                ProjectRole::where('user_id', $newUserId)->where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                $randomRole,
                ProjectRole::where('user_id', $newUserId)
                    ->where('project_id', $this->project->id)
                    ->value('role')
            );
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_user_is_added_as_curator_to_project()
    {
        $randomRole = config('epicollect.strings.project_roles.curator');
        $randomEmail = $this->faker->unique()->safeEmail();
        $payload = [
            'email' => $randomEmail,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );
            $response[0]->assertStatus(200);

            //assert role is added
            $this->assertEquals(1, User::where('email', $randomEmail)->count());
            $newUserId = User::where('email', $randomEmail)->first()->id;
            $this->assertEquals(
                1,
                ProjectRole::where('user_id', $newUserId)->where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }

    }

    public function test_user_is_added_as_collector_to_project()
    {
        $randomRole = config('epicollect.strings.project_roles.collector');
        $randomEmail = $this->faker->unique()->safeEmail();
        $payload = [
            'email' => $randomEmail,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );
            $response[0]->assertStatus(200);

            //assert role is added
            $this->assertEquals(1, User::where('email', $randomEmail)->count());
            $newUserId = User::where('email', $randomEmail)->first()->id;
            $this->assertEquals(
                1,
                ProjectRole::where('user_id', $newUserId)->where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }

    }

    public function test_user_is_added_as_viewer_to_project()
    {
        $randomRole = config('epicollect.strings.project_roles.viewer');
        $randomEmail = $this->faker->unique()->safeEmail();
        $payload = [
            'email' => $randomEmail,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );
            $response[0]->assertStatus(200);

            //assert role is added
            $this->assertEquals(1, User::where('email', $randomEmail)->count());
            $newUserId = User::where('email', $randomEmail)->first()->id;
            $this->assertEquals(
                1,
                ProjectRole::where('user_id', $newUserId)->where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }

    }

    public function test_manager_cannot_add_manager()
    {
        //create a manager user and add it to the project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        $randomRole = config('epicollect.strings.project_roles.manager');
        $randomEmail = $this->faker->unique()->safeEmail();
        $payload = [
            'email' => $randomEmail,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($manager)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(400)
                ->assertJsonStructure([
                    'errors' => [
                        '*' => [
                            'code',
                            'title',
                            'source',
                        ]
                    ]
                ])
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_344",
                                "title" => "You can only manage users with a lower role",
                                "source" => "user"
                            ],
                        ]
                    ]
                );

            //assert the role is not added, but user placeholder is created anyway
            $this->assertEquals(1, User::where('email', $randomEmail)->count());
            $newUserId = User::where('email', $randomEmail)->first()->id;
            $this->assertEquals(
                0,
                ProjectRole::where('user_id', $newUserId)->where('project_id', $this->project->id)->count()
            );
            //project should have only the creator role and the manager role
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.manager'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $manager->id)
                    ->value('role')
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_curator_cannot_add_project_members()
    {
        //create a curator user and add it to the project
        $curator = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $curator->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.curator')
        ]);

        $randomRole = config('epicollect.strings.project_roles.collector');
        $randomEmail = $this->faker->unique()->safeEmail();
        $payload = [
            'email' => $randomEmail,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($curator)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(404)
                ->assertJsonStructure([
                    'errors' => [
                        '*' => [
                            'code',
                            'title',
                            'source',
                        ]
                    ]
                ])
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_91",
                                "title" => "Sorry, you cannot perform this operation.",
                                "source" => "manage-users"
                            ],
                        ]
                    ]
                );

            //assert the user and the role are not added
            $this->assertEquals(0, User::where('email', $randomEmail)->count());
            //the project should have only the creator role and the curator role
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.curator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $curator->id)
                    ->value('role')
            );
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_collector_cannot_add_project_members()
    {
        //create a curator user and add it to the project
        $collector = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        $randomRole = config('epicollect.strings.project_roles.collector');
        $randomEmail = $this->faker->unique()->safeEmail();
        $payload = [
            'email' => $randomEmail,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($collector)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(404)
                ->assertJsonStructure([
                    'errors' => [
                        '*' => [
                            'code',
                            'title',
                            'source',
                        ]
                    ]
                ])
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_91",
                                "title" => "Sorry, you cannot perform this operation.",
                                "source" => "manage-users"
                            ],
                        ]
                    ]
                );

            //assert the user and the role are not added
            $this->assertEquals(0, User::where('email', $randomEmail)->count());
            //the project should have only the creator role and the curator role
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.collector'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $collector->id)
                    ->value('role')
            );
        } catch (\Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_viewer_cannot_add_project_members()
    {
        //create a curator user and add it to the project
        $viewer = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $viewer->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.viewer')
        ]);

        $randomRole = config('epicollect.strings.project_roles.collector');
        $randomEmail = $this->faker->unique()->safeEmail();
        $payload = [
            'email' => $randomEmail,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($viewer)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(404)
                ->assertJsonStructure([
                    'errors' => [
                        '*' => [
                            'code',
                            'title',
                            'source',
                        ]
                    ]
                ])
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_91",
                                "title" => "Sorry, you cannot perform this operation.",
                                "source" => "manage-users"
                            ],
                        ]
                    ]
                );

            //assert the user and the role are not added
            $this->assertEquals(0, User::where('email', $randomEmail)->count());
            //the project should have only the creator role and the curator role
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.viewer'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $viewer->id)
                    ->value('role')
            );
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            if ($response[0]->baseResponse->exception === null) {
                echo json_encode(['response' => $response[0]]) . PHP_EOL;
            } else {
                echo json_encode(['exception' => $response[0]->baseResponse->exception->getMessage()]) . PHP_EOL;
            }
        }
    }

    public function test_user_cannot_change_its_own_role_creator()
    {
        $randomRole = config('epicollect.strings.project_roles.collector');
        $payload = [
            'email' => $this->user->email,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(400)
                ->assertJsonStructure([
                    'errors' => [
                        '*' => [
                            'code',
                            'title',
                            'source',
                        ]
                    ]
                ])
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_217",
                                "title" => "You cannot change your own project role.",
                                "source" => "user"
                            ],
                        ]
                    ]
                );

            //the project should have only the creator role
            $this->assertEquals(
                1,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_user_cannot_change_its_own_role_manager()
    {
        //create a manager user and add it to the project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        $randomRole = config('epicollect.strings.project_roles.collector');
        $payload = [
            'email' => $manager->email,
            'role' => $randomRole
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($manager)
                ->post(
                    'myprojects/' . $this->project->slug . '/add-' . $randomRole,
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(400)
                ->assertJsonStructure([
                    'errors' => [
                        '*' => [
                            'code',
                            'title',
                            'source',
                        ]
                    ]
                ])
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_217",
                                "title" => "You cannot change your own project role.",
                                "source" => "user"
                            ],
                        ]
                    ]
                );

            //the project should have only the creator role and the new manager
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.manager'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $manager->id)
                    ->value('role')
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_creator_removes_manager_user()
    {
        //create a manager user and add it to the project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        $payload = [
            'email' => $manager->email
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/remove-role',
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(200);
            //the project should have only the creator role
            $this->assertEquals(
                1,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                0,
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $manager->id)
                    ->count()
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_creator_removes_curator_user()
    {
        //create a curator user and add it to the project
        $curator = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $curator->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.curator')
        ]);

        $payload = [
            'email' => $curator->email
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/remove-role',
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(200);
            //the project should have only the creator role
            $this->assertEquals(
                1,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                0,
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $curator->id)
                    ->count()
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_creator_removes_collector_user()
    {
        //create a collector user and add it to the project
        $collector = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        $payload = [
            'email' => $collector->email
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/remove-role',
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(200);
            //the project should have only the creator role
            $this->assertEquals(
                1,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                0,
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $collector->id)
                    ->count()
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_creator_removes_viewer_user()
    {
        //create a viewer user and add it to the project
        $viewer = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $viewer->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.viewer')
        ]);

        $payload = [
            'email' => $viewer->email
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/remove-role',
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(200);
            //the project should have only the creator role
            $this->assertEquals(
                1,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                0,
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $viewer->id)
                    ->count()
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_manager_removes_curator_user()
    {
        //create a curator user and add it to the project
        $curator = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $curator->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.curator')
        ]);

        //create a manager user and add it to the project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        $payload = [
            'email' => $curator->email
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($manager)
                ->post(
                    'myprojects/' . $this->project->slug . '/remove-role',
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(200);
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                0,
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $curator->id)
                    ->count()
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_manager_removes_collector_user()
    {
        //create a collector user and add it to the project
        $collector = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        //create a manager user and add it to the project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        $payload = [
            'email' => $collector->email
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($manager)
                ->post(
                    'myprojects/' . $this->project->slug . '/remove-role',
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(200);
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                0,
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $collector->id)
                    ->count()
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_manager_removes_viewer_user()
    {
        //create a viewer user and add it to the project
        $viewer = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $viewer->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.viewer')
        ]);

        //create a manager user and add it to the project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        $payload = [
            'email' => $viewer->email
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($manager)
                ->post(
                    'myprojects/' . $this->project->slug . '/remove-role',
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(200);
            $this->assertEquals(
                2,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                0,
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $viewer->id)
                    ->count()
            );
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_creator_cannot_remove_itself()
    {
        $payload = [
            'email' => $this->user->email
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($this->user)
                ->post(
                    'myprojects/' . $this->project->slug . '/remove-role',
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(400)
                ->assertJsonStructure([
                    'errors' => [
                        '*' => [
                            'code',
                            'title',
                            'source',
                        ]
                    ]
                ])
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_217",
                                "title" => "You cannot change your own project role.",
                                "source" => "user"
                            ],
                        ]
                    ]
                );
            //the project should have only the creator role
            $this->assertEquals(
                1,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_manager_cannot_remove_another_manager()
    {
        //create a manager user and add it to the project
        $manager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);

        //create another manager user and add it to the project
        $anotherManager = factory(User::class)->create();
        factory(ProjectRole::class)->create([
            'user_id' => $anotherManager->id,
            'project_id' => $this->project->id,
            'role' => config('epicollect.strings.project_roles.manager')
        ]);


        $payload = [
            'email' => $anotherManager->email
        ];

        $response = [];
        try {
            $response[] = $this->actingAs($manager)
                ->post(
                    'myprojects/' . $this->project->slug . '/remove-role',
                    $payload,
                    ['X-Requested-With' => 'XMLHttpRequest']
                );

            $response[0]->assertStatus(400)
                ->assertJsonStructure([
                    'errors' => [
                        '*' => [
                            'code',
                            'title',
                            'source',
                        ]
                    ]
                ])
                ->assertExactJson([
                        "errors" => [
                            [
                                "code" => "ec5_344",
                                "title" => "You can only manage users with a lower role",
                                "source" => "user"
                            ],
                        ]
                    ]
                );
            //the project should have only 3 members yet
            $this->assertEquals(
                3,
                ProjectRole::where('project_id', $this->project->id)->count()
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.creator'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $this->user->id)
                    ->value('role')
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.manager'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $anotherManager->id)
                    ->value('role')
            );
            $this->assertEquals(
                config('epicollect.strings.project_roles.manager'),
                ProjectRole::where('project_id', $this->project->id)
                    ->where('user_id', $manager->id)
                    ->value('role')
            );

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

}

