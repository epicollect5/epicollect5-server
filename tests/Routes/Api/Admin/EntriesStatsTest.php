<?php

namespace Tests\Routes\Api\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class EntriesStatsTest extends TestCase
{

    use WithoutMiddleware;
    use DatabaseTransactions;

    /**
     * Test entries stats api json response
     *
     * @return void
     */
    public function testEntriesStatsApiResponse()
    {


        $apiContentTypeHeaderKey = config('epicollect.setup.api.responseContentTypeHeaderKey');
        $apiContentTypeHeaderValue = config('epicollect.setup.api.responseContentTypeHeaderValue');

        $response = $this->json('GET', '/api/internal/admin/entries-stats');

        //dd($response); // add this temporarily

        $response->assertStatus(200)
            ->assertHeader($apiContentTypeHeaderKey, $apiContentTypeHeaderValue)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'entries' => [
                        'total' => [
                            'private',
                            'public'
                        ],
                        'today' => [
                            'private',
                            'public'
                        ],
                        'week' => [
                            'private',
                            'public'
                        ],
                        'month' => [
                            'private',
                            'public'
                        ],
                        'year' => [
                            'private',
                            'public'
                        ],
                    ],
                    'branch_entries' => [
                        'total' => [
                            'private',
                            'public'
                        ],
                        'today' => [
                            'private',
                            'public'
                        ],
                        'week' => [
                            'private',
                            'public'
                        ],
                        'month' => [
                            'private',
                            'public'
                        ],
                        'year' => [
                            'private',
                            'public'
                        ]
                    ]
                ]
            ]);
    }
}
