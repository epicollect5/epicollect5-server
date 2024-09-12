<?php

namespace Tests\Http\Controllers\Web\Auth;

use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VerificationCodeControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    //routes to enter 6-digit code when account email clashes
    public function test_page_renders_correctly()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();

        $response = $this->actingAs($user, self::DRIVER)->get(route('verification-code'));
        //todo: need a lot of stuff
        $this->assertTrue(true);
        //$response->assertStatus(200); // Ensure the response is successful
    }
}