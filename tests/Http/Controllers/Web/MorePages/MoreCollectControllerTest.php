<?php

namespace Tests\Http\Controllers\Web\MorePages;

use Tests\TestCase;

class MoreCollectControllerTest extends TestCase
{
    public function test_more_create_renders_correctly()
    {
        $response = $this
            ->get('more-collect')
            ->assertStatus(200);
        $this->assertEquals('more-pages.more_collect', $response->original->getName());
    }
}