<?php

namespace Tests\Routes\Api\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ProjectsStatsAdminInternalTest extends TestCase
{
    use WithoutMiddleware;
    use DatabaseTransactions;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_projects_stats_api_response()
    {
        $apiContentTypeHeaderKey = config('epicollect.setup.api.responseContentTypeHeaderKey');
        $apiContentTypeHeaderValue = config('epicollect.setup.api.responseContentTypeHeaderValue');

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
