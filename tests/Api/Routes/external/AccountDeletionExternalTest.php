<?php

namespace Tests;


use ec5\Mail\UserAccountDeletionUser;
use ec5\Mail\UserAccountDeletionAdmin;
use ec5\Models\Users\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class AccountDeletionExternalTest extends TestCase
{
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

    public function testValidRequest()
    {
        $manager = User::where('email', 'manager@unit.tests')->first();
        $manager->state = 'active';

        //Login manager user as passwordless to get a JWT 
        Auth::guard('api_external')->login($manager, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT
        Mail::fake();
        $this->json('POST', '/api/profile/account-deletion-request', [], [
            'Authorization' => 'Bearer ' . $jwt
        ])
            ->assertStatus(200)
            ->assertExactJson([
                "data" =>  [
                    "id" => "account-deletion-request",
                    "accepted" => true
                ]
            ]);

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionUser::class, function ($mail) use ($manager) {
            return $mail->hasTo($manager->email);
        });
        //assert a message was sent to admin
        Mail::assertSent(UserAccountDeletionAdmin::class, function ($mail) {
            return $mail->hasTo(env('SYSTEM_EMAIL'));
        });
    }

    public function testInvalidRequest()
    {
        //account deletion request without JWT
        $response = $this->json('POST', '/api/profile/account-deletion-request', [], []);
        $response->assertStatus(404)
            ->assertExactJson([
                'errors' => [
                    [
                        "code" => "ec5_219",
                        "title" => "Page not found.",
                        "source" => "auth"
                    ]
                ],
            ]);
    }
}
