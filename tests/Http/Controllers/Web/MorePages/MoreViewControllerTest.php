<?php

namespace Tests\Http\Controllers\Web\MorePages;

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
}