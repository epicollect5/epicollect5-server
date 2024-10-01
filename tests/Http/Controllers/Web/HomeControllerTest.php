<?php

namespace Tests\Http\Controllers\Web;

use Auth;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use DatabaseTransactions;

    const string DRIVER = 'web';

    public function test_home_page_renders_correctly()
    {
        $response = $this
            ->get(route('home'))
            ->assertStatus(200);
    }

    public function test_home_page_renders_correctly_when_logged_in()
    {
        $user = factory(User::class)->create();
        Auth::login($user);
        $response = $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertStatus(200);
    }
}