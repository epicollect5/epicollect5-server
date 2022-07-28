<?php

namespace Tests\Project;

use ec5\Http\Validation\Project\RuleCreateRequest;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    protected $validator;

    public function setUp()
    {
        parent::setUp();
        $this->validator = new RuleCreateRequest();
    }

    public function testRequest(){

    }


}
