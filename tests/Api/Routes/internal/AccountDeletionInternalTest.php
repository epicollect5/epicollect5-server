<?php

namespace Tests;

use ec5\Mail\UserAccountDeletionUser;
use ec5\Mail\UserAccountDeletionAdmin;
use ec5\Mail\UserAccountDeletionConfirmation;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Users\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AccountDeletionInternalTest extends TestCase
{
    //to restore DB after tests
    use DatabaseTransactions;

    //internal routes use the default 'web; guard
    const DRIVER = 'web';

    protected $privateProjectSlug;
    protected $publicProjectSlug;

    public function setup()
    {
        parent::setUp();
        $this->privateProjectSlug = 'ec5-private';
        $this->publicProjectSlug = 'ec5-public';
    }

    /**
     * Test an authenticated user's routes
     */
    public function testValidRequest()
    {
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->email = env('CREATOR_EMAIL');
        $user->id = env('CREATOR_ID');

        //account deletion request    
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
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

    //no user, fail
    public function testInvalidRequest()
    {
        $this->json('POST', '/api/internal/profile/account-deletion-request', [])
            ->assertStatus(404)
            ->assertExactJson([
                "errors" =>  [
                    [
                        "code" => "ec5_219",
                        "title" => "Page not found.",
                        "source" => "auth"
                    ]
                ]
            ]);
    }

    public function testAccountDeletionPerformed()
    {
        //create a fake user ans save it to DB
        $user = factory(User::class)->create();
        $user->state = 'active';
        $user->email = 'user-to-be-deleted@example.com';
        $user->save();

        //account deletion    
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
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

        //try account deletion, not performed because user has role
        Mail::fake();
        $this->actingAs($user, SELF::DRIVER)
            ->json('POST', '/api/internal/profile/account-deletion-request', [])
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
