<?php

namespace Tests\Http\Controllers\Web\Projects;

use Tests\TestCase;

class ListedProjectsControllerTest extends TestCase
{
    public function test_category_page_renders_correctly()
    {
        $categories = [
            'general',
            'social',
            'art',
            'humanities',
            'biology',
            'economics',
            'science'
        ];

        foreach ($categories as $category) {
            $response = $this->call('GET', 'projects/' . $category);
            $response->assertStatus(200);
        }
    }
}