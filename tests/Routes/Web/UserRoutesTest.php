<?php

namespace Tests\Routes\Web;

use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserRoutesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test an authenticated user's routes
     */
    public function testActiveAuthUserRoutes()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $user->state = 'active';

        $response = $this->get('/');
        $response->assertStatus(200);

        $response = $this->get('/myprojects');
        $response->assertStatus(200);

        $response = $this->get('/profile');
        $response->assertStatus(200);

        $response = $this->get('/myprojects/create');
        $response->assertStatus(200);

        $response = $this->get('/login');
        $response->assertRedirect('/');

        $response = $this->get('/logout');
        $response->assertRedirect('/');

        $response = $this->get('/admin');
        $response->assertStatus(422);
    }

    /**
     * Test guest user routes
     */
    public function testGuestUserRoutes()
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $response = $this->get('/login');
        $response->assertStatus(200);

        $response = $this->get('/myprojects');
        $response->assertRedirect('/login');

        $response = $this->get('/myprojects/create');
        $response->assertRedirect('/login');

        $response = $this->get('/logout');
        $response->assertRedirect('/login');

        $response = $this->get('/profile');
        $response->assertRedirect('/login');

        $response = $this->get('/admin');
        $response->assertStatus(422);
    }

    /**
     * Test admin user routes
     */
    public function testActiveAdminUserRoutes()
    {
        $user = factory(User::class)->create();
        $user->server_role = 'admin';
        $user->state = 'active';
        $this->actingAs($user);

        $response = $this->get('/admin');
        $response->assertStatus(422);
    }

    /**
     * Test admin user routes
     */
    public function testActiveSuperAdminUserRoutes()
    {
        $user = factory(User::class)->create();
        $user->server_role = 'superadmin';
        $user->state = 'active';
        $this->actingAs($user);

        $response = $this->get('/admin');
        $response->assertStatus(422);
    }

    /**
     * Test admin user routes
     */
    public function testDisabledAuthUserRoutes()
    {

        $user = factory(User::class)->create();
        $user->state = 'disabled';
        $this->actingAs($user);

        $response = $this->get('/myprojects');
        $response->assertRedirect('/login');

        $response = $this->get('/myprojects/create');
        $response->assertRedirect('/login');

        $response = $this->get('/admin');
        $response->assertStatus(422);
    }
}
