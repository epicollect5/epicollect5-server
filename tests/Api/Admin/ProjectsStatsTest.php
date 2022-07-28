<?php

namespace Tests\Api\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Config;

class ProjectsStatsTest extends TestCase
{
    use WithoutMiddleware;
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testProjectsStatsApiResponse()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");

        $apiContentTypeHeaderKey = Config('ec5Api.responseContentTypeHeaderKey');
        $apiContentTypeHeaderValue = Config('ec5Api.responseContentTypeHeaderValue');

        $this->json('GET', 'api/internal/admin/projects-stats')
            ->assertStatus(200)
            ->assertHeader($apiContentTypeHeaderKey, $apiContentTypeHeaderValue)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'projects' => [
                        'total' => [
                            'private' => [
                                'hidden',
                                'listed'
                            ],
                            'public' => [
                                'hidden',
                                'listed'
                            ]
                        ],
                        'today' => [
                            'private' => [
                                'hidden',
                                'listed'
                            ],
                            'public' => [
                                'hidden',
                                'listed'
                            ]
                        ],
                        'week' => [
                            'private' => [
                                'hidden',
                                'listed'
                            ],
                            'public' => [
                                'hidden',
                                'listed'
                            ]
                        ],
                        'month' => [
                            'private' => [
                                'hidden',
                                'listed'
                            ],
                            'public' => [
                                'hidden',
                                'listed'
                            ]
                        ],
                        'year' => [
                            'private' => [
                                'hidden',
                                'listed'
                            ],
                            'public' => [
                                'hidden',
                                'listed'
                            ]
                        ]
                    ]
                ]
            ]);
    }
}
