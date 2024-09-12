<?php

namespace Tests\Http\Validation\Project;

use ec5\Http\Validation\Project\RuleCreateRequest;
use ec5\Models\Project\Project;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class RuleCreateRequestTest extends TestCase
{
    use DatabaseTransactions, Assertions;

    private $ruleCreateRequest;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleCreateRequest = new RuleCreateRequest();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->ruleCreateRequest->resetErrors();
    }

    public function test_missing_required_project_name()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => '',
            'slug' => 'a-valid-slug',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'ec5_21'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_project_name_too_short()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'ab',
            'slug' => 'ab',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'Project name must be at least 3 chars long!'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_project_name_too_long()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'VeoXGKc6MDsqgxzaSmsGeDsZONpyurpCey2AOuBUQ4UwPCfxaH7',
            'slug' => mb_strtolower('VeoXGKc6MDsqgxzaSmsGeDsZONpyurpCey2AOuBUQ4UwPCfxaH7'),
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'Project name must be maximum 50 chars long!'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_ec5_unreserved_name_server_role_basic()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'EC5 Project Name',
            'slug' => 'a-valid-slug',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'ec5_235'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_ec5_unreserved_name_server_role_admin()
    {
        $user = factory(User::class)->create(
            ['server_role' => 'admin']
        );
        $this->actingAs($user);
        $data = [
            'name' => 'EC5 Project Name',
            'slug' => 'a-valid-slug',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertFalse($this->ruleCreateRequest->hasErrors());
    }

    public function test_ec5_unreserved_name_server_role_superadmin()
    {
        $user = factory(User::class)->create(
            ['server_role' => 'superadmin']
        );
        $this->actingAs($user);
        $data = [
            'name' => 'EC5 Project Name',
            'slug' => 'a-valid-slug',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertFalse($this->ruleCreateRequest->hasErrors());
    }

    public function test_project_name_invalid_chars_1()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'Project#',
            'slug' => 'a-valid-slug',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'ec5_205'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_project_name_invalid_chars_2()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'Project Name-----',
            'slug' => 'project-name-----',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'ec5_205'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_missing_required_slug()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'The Project Name',
            'slug' => '',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'slug' => [
                'ec5_21'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_invalid_slug()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'Create',
            'slug' => 'create',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'slug' => [
                'The selected slug is invalid.'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_valid_values()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'A valid slug',
            'slug' => 'a-valid-slug',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertFalse($this->ruleCreateRequest->hasErrors());
    }

    public function test_duplicate_project_name_not_archived()
    {
        // Add a test project to the 'projects' table with the same slug
        // Since 'archived' status is not in this record, the rule should fail
        factory(Project::class)->create([
            'name' => 'A duplicated name',
            'slug' => 'a-duplicated-slug',
            'status' => 'active'
        ]);
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'A duplicated name',
            'slug' => 'a-duplicated-slug',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'slug' => [
                'ec5_85'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_duplicate_project_name_but_archived()
    {
        // Add a test project to the 'projects' table with the same slug
        factory(Project::class)->create([
            'name' => 'A duplicated name',
            'slug' => 'a-duplicated-slug',
            'status' => 'archived'
        ]);
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'A duplicated name',
            'slug' => 'a-duplicated-slug',
            'small_description' => 'This is a small description test',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertFalse($this->ruleCreateRequest->hasErrors());
    }

    public function test_small_desc_too_short()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $testProjectName = str_replace('-', '', Uuid::uuid4()->toString());
        $data = [
            'name' => $testProjectName,
            'slug' => $testProjectName,
            'small_description' => 'Th',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'small_description' => [
                'Small description must be at least 15 chars long!'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_form_name_too_short()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $testProjectName = str_replace('-', '', Uuid::uuid4()->toString());
        $data = [
            'name' => $testProjectName,
            'slug' => $testProjectName,
            'small_description' => 'This is a valid small description',
            'form_name' => '',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'form_name' => [
                'ec5_21'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_small_desc_too_long()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $testProjectName = str_replace('-', '', Uuid::uuid4()->toString());
        $data = [
            'name' => $testProjectName,
            'slug' => $testProjectName,
            'small_description' => '7NVtjQwA9888aVGEg8uSFT2kGlNbzxfhqbw0HLEq8FKyubgYwLVLw98mNz1hf8hk84rYDYKM40NdnWo0WabazNloLkE50Kt4cXfsz',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'small_description' => [
                'Small description must be maximum 100 chars long!'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_small_desc_missing()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $testProjectName = str_replace('-', '', Uuid::uuid4()->toString());
        $data = [
            'name' => $testProjectName,
            'slug' => $testProjectName,
            'small_description' => '',
            'form_name' => 'Form One',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'small_description' => [
                'ec5_21'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_form_name_too_long()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $testProjectName = str_replace('-', '', Uuid::uuid4()->toString());
        $data = [
            'name' => $testProjectName,
            'slug' => $testProjectName,
            'small_description' => 'This is a valid small description',
            'form_name' => 'Cjke6Efwy2wsZ5VFUZozBAqvaHlNX9UXSyvlH88CfvyBwWoEUqF',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'form_name' => [
                'ec5_44'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_form_name_invalid_chars_1()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $testProjectName = str_replace('-', '', Uuid::uuid4()->toString());
        $data = [
            'name' => $testProjectName,
            'slug' => $testProjectName,
            'small_description' => 'This is a valid small description',
            'form_name' => 'Form One#',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'form_name' => [
                'ec5_205'
            ],
        ], $this->ruleCreateRequest->errors());
    }

    public function test_form_name_invalid_chars_2()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $testProjectName = str_replace('-', '', Uuid::uuid4()->toString());
        $data = [
            'name' => $testProjectName,
            'slug' => $testProjectName,
            'small_description' => 'This is a valid small description',
            'form_name' => 'Form One-',
            'access' => 'private'
        ];

        $this->ruleCreateRequest->validate($data);
        $this->assertTrue($this->ruleCreateRequest->hasErrors());
        $this->assertArraySubset([
            'form_name' => [
                'ec5_205'
            ],
        ], $this->ruleCreateRequest->errors());
    }
}