<?php

namespace Tests;


use ec5\Mail\UserAccountDeletionUser;
use ec5\Mail\UserAccountDeletionAdmin;
use ec5\Models\Users\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use ec5\Mail\UserAccountDeletionConfirmation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ec5\Models\Eloquent\ProjectRole;


class AccountDeletionExternalTest extends TestCase
{
    /**
     * Test an authenticated user's routes
     * imp: avoid $this->actingAs($user, 'api_external');
     * imp: as that create a valid user object therefore bypassing
     * imp: jwt validation. We need to send a valid token per each request
     * imp: instead.
     */

    use DatabaseTransactions;

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

    public function testAccountDeletion()
    {
        //create a fake user ans save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->email = 'user-to-be-deleted@example.com';
        $user->save();

        //Login manager user as passwordless to get a JWT 
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT
        Mail::fake();
        $this->json('POST', '/api/profile/account-deletion-request', [], [
            'Authorization' => 'Bearer ' . $jwt
        ])
            ->assertStatus(200)
            ->assertExactJson([
                "data" =>  [
                    "id" => "account-deletion-performed",
                    "deleted" => true
                ]
            ]);

        // Assert a message was sent to the given users...
        Mail::assertSent(UserAccountDeletionConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function testAccountDeletionNotPerformed()
    {
        //create a project role (random project, random user)
        $projectRole = factory(ProjectRole::class)->create();
        //get that user
        $user = User::where('id',  $projectRole->user_id)->first();

        //Login manager user as passwordless to get a JWT 
        Auth::guard('api_external')->login($user, false);
        $jwt = Auth::guard('api_external')->authorizationResponse()['jwt'];

        //account deletion request with valid JWT, not performed automatically since user has role
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
        Mail::assertSent(UserAccountDeletionUser::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
        //assert a message was sent to admin
        Mail::assertSent(UserAccountDeletionAdmin::class, function ($mail) {
            return $mail->hasTo(env('SYSTEM_EMAIL'));
        });
    }
}
