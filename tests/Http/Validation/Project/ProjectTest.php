<?php

namespace Tests\Http\Validation\Project;

use ec5\Http\Validation\Project\RuleCreateRequest;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    protected RuleCreateRequest $validator;

    public function setUp(): void
    {
        parent::setUp();
        $this->validator = new RuleCreateRequest();
    }

    public function test_request()
    {
        $this->assertTrue(true);
    }
}
