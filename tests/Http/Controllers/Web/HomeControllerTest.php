<?php

namespace Tests\Http\Controllers\Web;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    public function test_home_page_renders_correctly()
    {
        $response = $this
            ->get(route('home'))
            ->assertStatus(200);
    }
}