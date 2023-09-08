<?php

namespace Tests;


use ec5\Mail\UserAccountDeletionUser;
use ec5\Mail\UserAccountDeletionAdmin;
use ec5\Models\Users\User;
use ec5\Models\Eloquent\Project;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ExternalRoutesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test an authenticated user's routes
     * imp: avoid $this->actingAs($user, 'api_external');
     * imp: as that create a valid user object therefore bypassing
     * imp: jwt validation. We need to send a valid token per each request
     * imp: instead.
     */

    protected $privateProjectSlug;
    protected $publicProjectSlug;

    public function setup()
    {
        parent::setUp();
        $this->privateProjectSlug = 'ec5-private';
        $this->publicProjectSlug = 'ec5-public';
    }

    public function testPrivateExternalRoutesWithJWT()
    {
        $mock = factory(User::class)->create();
        $user = User::where('email', $mock->email)->first();

        //hack: do not use this api_external
        //$this->actingAs($user, 'api_external');

        //Login manager user as passwordless to get a JWT 
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];
        // dd($jwt);

        //token valid and member? get in
        $response = $this->json('GET', 'api/project/ec5-private', [], [
            'Authorization' => 'Bearer ' . $jwt
        ]);
        //dd($response);


        // //token valid but not a member? get out
        // $this->json('GET', 'ec5-api-external-routes-tests', [], [
        //     'Authorization' => 'Bearer ' . $jwt
        // ])->assertStatus(200);
    }

    public function testPrivateExternalRoutesWithoutJWT()
    {
        //create fake private project
        $project = factory(Project::class)->create([
            'slug' => 'ec5-private',
            'access' => 'private'
        ]);

        //try to access without authenticating
        $response = $this->json('GET', 'api/project/ec5-private', [])
            ->assertStatus(404)
            ->assertExactJson(['errors' => [
                [
                    "code" => "ec5_77",
                    "title" => "This project is private. Please log in.",
                    "source" => "middleware"
                ]
            ]]);
    }

    /**
     * Test public user routes
     */
    public function testPublicExternalRoutes()
    {
    }
}
