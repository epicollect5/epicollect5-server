<?php

use ec5\Models\User\User;

class CleanUp extends \Tests\TestCase
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
    }
}