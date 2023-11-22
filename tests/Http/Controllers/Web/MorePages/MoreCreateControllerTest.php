<?php

namespace Tests\Http\Controllers\Web\MorePages;

use Tests\TestCase;

class MoreCreateControllerTest extends TestCase
{
    public function test_more_create_renders_correctly()
    {
        $response = $this
            ->get('more-create')
            ->assertStatus(200);
        $this->assertEquals('more-pages.more_create', $response->original->getName());
    }
}