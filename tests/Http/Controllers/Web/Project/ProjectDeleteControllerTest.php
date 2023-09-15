<?php

namespace Http\Controllers\Web\Project;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProjectDeleteControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp()
    {
        parent::setUp();
    }

    public function test_soft_delete()
    {

    }
}

