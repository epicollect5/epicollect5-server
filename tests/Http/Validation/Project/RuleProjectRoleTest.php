<?php

namespace Tests\Http\Validation\Project;

use Tests\TestCase;
use ec5\Http\Validation\Project\RuleProjectRole;
use Faker\Factory as Faker;

class RuleProjectRoleTest extends TestCase
{
    private $ruleProjectRole;
    private $faker;
    private $roles;


    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->faker = Faker::create();
        $this->ruleProjectRole = new RuleProjectRole();
        $this->roles = config('epicollect.permissions.projects.roles.creator');

    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->ruleProjectRole->resetErrors();
    }

    public function test_valid_payload()
    {
        for ($i = 0; $i < 10; $i++) {
            $payload = [
                'email' => $this->faker->unique()->safeEmail(),
                'role' => $this->faker->randomElement($this->roles)
            ];
            $this->ruleProjectRole->validate($payload);
            $this->assertFalse($this->ruleProjectRole->hasErrors());
            $this->ruleProjectRole->resetErrors();
        }
    }
}