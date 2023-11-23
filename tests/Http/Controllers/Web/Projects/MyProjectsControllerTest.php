<?php

namespace Tests\Http\Controllers\Web\Projects;

use ec5\Models\Eloquent\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MyProjectsControllerTest extends TestCase
{
    use DatabaseTransactions;
    
    const DRIVER = 'web';

    public function test_my_projects_page_renders_correctly()
    {
        //create mock user
        $user = factory(User::class)->create();

        $response = $this
            ->actingAs($user, self::DRIVER)
            ->get(route('my-projects'));
        $response->assertStatus(200);
    }
}