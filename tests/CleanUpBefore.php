<?php

namespace Tests;

use ec5\Models\Project\Project;
use ec5\Models\User\User;

class CleanUpBefore extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->clearDatabase([]);
    }

    public function test_database_is_cleared()
    {
        $this->assertEquals(
            0,
            User::where('email', 'like', '%@example.com')->count()
        );

        $this->assertEquals(
            0,
            Project::where('created_by', '>=', config('testing.TEST_USER_ID_BASE'))->count()
        );
    }
}
