<?php

namespace Tests\Http\Controllers\Web\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_renders_correctly()
    {
        $response = $this->get(route('login')); // Replace with the actual route or URL to your view
        $response->assertStatus(200); // Ensure the response is successful
    }
}