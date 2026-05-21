<?php

namespace Tests\Routes\Api\external;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_login_returns_all_methods()
    {
        config(['auth.auth_methods' => ['google', 'apple', 'passwordless']]);

        $response = $this->json('GET', 'api/login');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'type' => 'login',
                'login' => [
                    'methods' => ['google', 'apple', 'passwordless'],
                ],
            ],
        ]);
        // Should not include 'local'
        $methods = $response->json('data.login.methods');
        $this->assertNotContains('local', $methods);
        // Should include Google client ID
        $this->assertArrayHasKey('google', $response->json('data.login.auth_ids'));
        $this->assertArrayHasKey('CLIENT_ID', $response->json('data.login.auth_ids.google'));
    }

    public function test_get_login_returns_google_only()
    {
        config(['auth.auth_methods' => ['google']]);

        $response = $this->json('GET', 'api/login');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'type' => 'login',
                'login' => [
                    'methods' => ['google'],
                ],
            ],
        ]);
    }

    public function test_get_login_returns_apple_only()
    {
        config(['auth.auth_methods' => ['apple']]);

        $response = $this->json('GET', 'api/login');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'type' => 'login',
                'login' => [
                    'methods' => ['apple'],
                ],
            ],
        ]);
        // No Google auth_ids when google is not in methods
        $this->assertEmpty($response->json('data.login.auth_ids'));
    }

    public function test_get_login_returns_passwordless_only()
    {
        config(['auth.auth_methods' => ['passwordless']]);

        $response = $this->json('GET', 'api/login');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'type' => 'login',
                'login' => [
                    'methods' => ['passwordless'],
                ],
            ],
        ]);
        $this->assertEmpty($response->json('data.login.auth_ids'));
    }

    public function test_get_login_returns_no_local_method()
    {
        config(['auth.auth_methods' => ['google', 'apple', 'passwordless']]);

        $response = $this->json('GET', 'api/login');

        $methods = $response->json('data.login.methods');
        $this->assertNotContains('local', $methods);
        $this->assertNotContains('ldap', $methods);
    }
}
