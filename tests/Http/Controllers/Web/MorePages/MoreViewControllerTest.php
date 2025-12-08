<?php

namespace Tests\Http\Controllers\Web\MorePages;

use Auth;
use ec5\Models\User\User;
use Tests\TestCase;

class MoreViewControllerTest extends TestCase
{
    public function test_more_create_renders_correctly()
    {
        $response = $this
            ->get('more-view')
            ->assertStatus(200);
        $this->assertEquals('more-pages.more_view', $response->original->getName());
    }

    public function test_more_create_renders_correctly_when_logged_in()
    {
        $user = factory(User::class)->create();
        Auth::login($user);
        $response = $this
            ->actingAs($user)
            ->get('more-view')
            ->assertStatus(200);
        $this->assertEquals('more-pages.more_view', $response->original->getName());
    }
}