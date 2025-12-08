<?php

namespace Tests\Http\Validation\Project;

use ec5\Http\Validation\Project\RuleImportRequest;
use ec5\Models\Project\Project;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RuleImportRequestTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private RuleImportRequest $ruleImportRequest;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleImportRequest = new RuleImportRequest();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->ruleImportRequest->resetErrors();
    }

    public function test_missing_required_project_name()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => '',
            'slug' => 'a-valid-slug',
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertTrue($this->ruleImportRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'ec5_21'
            ],
        ], $this->ruleImportRequest->errors());
    }

    public function test_project_name_too_short()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'ab',
            'slug' => 'ab',
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertTrue($this->ruleImportRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'Project name must be at least 3 chars long!'
            ],
        ], $this->ruleImportRequest->errors());
    }

    public function test_project_name_too_long()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'VeoXGKc6MDsqgxzaSmsGeDsZONpyurpCey2AOuBUQ4UwPCfxaH7',
            'slug' => mb_strtolower('VeoXGKc6MDsqgxzaSmsGeDsZONpyurpCey2AOuBUQ4UwPCfxaH7'),
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertTrue($this->ruleImportRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'Project name must be maximum 50 chars long!'
            ],
        ], $this->ruleImportRequest->errors());
    }

    public function test_ec5_unreserved_name_server_role_basic()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'EC5 Project Name',
            'slug' => 'a-valid-slug',
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertTrue($this->ruleImportRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'ec5_235'
            ],
        ], $this->ruleImportRequest->errors());
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
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertFalse($this->ruleImportRequest->hasErrors());
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
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertFalse($this->ruleImportRequest->hasErrors());
    }

    public function test_project_name_invalid_chars_1()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'Project#',
            'slug' => 'a-valid-slug',
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertTrue($this->ruleImportRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'ec5_205'
            ],
        ], $this->ruleImportRequest->errors());
    }

    public function test_project_name_invalid_chars_2()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'Project Name-----',
            'slug' => 'project-name-----',
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertTrue($this->ruleImportRequest->hasErrors());
        $this->assertArraySubset([
            'name' => [
                'ec5_205'
            ],
        ], $this->ruleImportRequest->errors());
    }

    public function test_valid_values()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'A valid slug',
            'slug' => 'a-valid-slug',
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertFalse($this->ruleImportRequest->hasErrors());
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
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertTrue($this->ruleImportRequest->hasErrors());
        $this->assertArraySubset([
            'slug' => [
                'ec5_85'
            ],
        ], $this->ruleImportRequest->errors());
    }

    public function test_duplicate_project_name_but_archived()
    {
        // Add a test project to the 'projects' table with the same slug
        factory(Project::class)->create([
            'name' => 'A duplicated name',
            'slug' => 'a-duplicated-slug',
            'status' => config('epicollect.strings.project_status.archived')
        ]);
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $data = [
            'name' => 'A duplicated name',
            'slug' => 'a-duplicated-slug',
            'file' => 'test-file.json'
        ];

        $this->ruleImportRequest->validate($data);
        $this->assertFalse($this->ruleImportRequest->hasErrors());
    }

    //todo: test file name and size
}
