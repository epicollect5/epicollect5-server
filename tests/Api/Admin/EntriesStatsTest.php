<?php

namespace Tests\Api\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Config;

class EntriesStatsTest extends TestCase
{

    use WithoutMiddleware;

    /**
     * Test entries stats api json response
     *
     * @return void
     */
    public function testEntriesStatsApiResponse()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");

        $apiContentTypeHeaderKey = Config('ec5Api.responseContentTypeHeaderKey');
        $apiContentTypeHeaderValue = Config('ec5Api.responseContentTypeHeaderValue');

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
