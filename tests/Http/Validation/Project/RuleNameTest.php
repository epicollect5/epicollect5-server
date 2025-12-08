<?php

namespace Tests\Http\Validation\Project;

use ec5\Http\Validation\Project\RuleName;
use ec5\Models\Project\Project;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RuleNameTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    private RuleName $ruleName;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleName = new RuleName();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->ruleName->resetErrors();
    }

    public function test_missing_required_name()
    {
        $data = [
            'name' => '',
            'slug' => 'a-valid-slug',
        ];

        $this->ruleName->validate($data);
        $this->assertTrue($this->ruleName->hasErrors());
    }

    public function test__name_too_short()
    {
        $data = [
            'name' => 'ab',
            'slug' => 'ab',
        ];

        $this->ruleName->validate($data);
        $this->assertTrue($this->ruleName->hasErrors());
    }

    public function test__name_too_long()
    {
        $data = [
            'name' => 'VeoXGKc6MDsqgxzaSmsGeDsZONpyurpCey2AOuBUQ4UwPCfxaH7',
            'slug' => mb_strtolower('VeoXGKc6MDsqgxzaSmsGeDsZONpyurpCey2AOuBUQ4UwPCfxaH7'),
        ];

        $this->ruleName->validate($data);
        $this->assertTrue($this->ruleName->hasErrors());
    }

    public function test_name_invalid_chars_1()
    {
        $data = [
            'name' => 'Project#',
            'slug' => 'a-valid-slug',
        ];

        $this->ruleName->validate($data);
        $this->assertTrue($this->ruleName->hasErrors());
    }

    public function test_name_invalid_chars_2()
    {
        $data = [
            'name' => 'Project-op',
            'slug' => 'a-valid-slug',
        ];

        $this->ruleName->validate($data);
        $this->assertTrue($this->ruleName->hasErrors());
    }

    public function test_missing_required_slug()
    {
        $data = [
            'name' => 'The Project Name',
            'slug' => '',
        ];

        $this->ruleName->validate($data);
        $this->assertTrue($this->ruleName->hasErrors());
    }

    public function test_invalid_slug()
    {
        $data = [
            'name' => 'Create',
            'slug' => 'create',
        ];

        $this->ruleName->validate($data);
        $this->assertTrue($this->ruleName->hasErrors());
    }

    public function test_valid_values()
    {
        $data = [
            'name' => 'A valid slug',
            'slug' => 'a-valid-slug',
        ];

        $this->ruleName->validate($data);
        $this->assertFalse($this->ruleName->hasErrors());
    }

    public function test_duplicate_value_not_archived()
    {
        // Add a test project to the 'projects' table with the same slug
        // Since 'archived' status is not in this record, the rule should fail
        factory(Project::class)->create([
            'name' => 'A duplicated slug',
            'slug' => 'a-duplicated-slug',
            'status' => 'active'
        ]);

        $data = [
            'name' => 'A duplicated slug',
            'slug' => 'a-duplicated-slug'
        ];

        $this->ruleName->validate($data);
        $this->assertTrue($this->ruleName->hasErrors());
        $this->assertArraySubset([
            'slug' => [
                'ec5_85'
            ],
        ], $this->ruleName->errors());
    }

    public function test_duplicate_value_but_archived()
    {
        // Add a test project to the 'projects' table with the same slug
        factory(Project::class)->create([
            'name' => 'A duplicated slug',
            'slug' => 'a-duplicated-slug',
            'status' => 'archived'
        ]);

        $data = [
            'name' => 'A duplicated slug',
            'slug' => 'a-duplicated-slug'
        ];

        $this->ruleName->validate($data);
        $this->assertFalse($this->ruleName->hasErrors());
    }
}
