<?php

namespace Tests\Http\Controllers\Web\Auth;

use ec5\Models\Users\User;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    const DRIVER = 'web';

    public function test_profile_page_renders_correctly()
    {
        //create mock user
        $user = factory(User::class)->create();

        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get(route('profile'));
        $response->assertStatus(200);
    }
}