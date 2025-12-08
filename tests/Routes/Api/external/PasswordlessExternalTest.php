<?php

namespace Tests\Routes\Api\external;


use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Mail\UserPasswordlessApiMail;
use ec5\Models\User\UserPasswordlessApi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordlessExternalTest extends TestCase
{
    //to reset database after tests
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

    public function setup(): void
    {
        parent::setUp();
        $this->privateProjectSlug = 'ec5-private';
        $this->publicProjectSlug = 'ec5-public';
    }

    public function testSendCode()
    {
        $email = config('testing.MANAGER_EMAIL');

        //send a code to user for authentication
        Mail::fake();
        $this->json('POST', '/api/login/passwordless/code', [
            'email' => $email
        ], [])
            ->assertStatus(200)
            ->assertExactJson([
                "data" => [
                    "code" => "ec5_372",
                    "title" => "Email sent successfully."
                ]
            ]);

        // Assert a message was sent to the given users...
        Mail::assertSent(UserPasswordlessApiMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    public function testLogin()
    {
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessApi::class)
            ->create([
                'email' => $email,
                'code' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $this->json('POST', '/api/login/passwordless', [
            'email' => $email,
            'code' => $code
        ], [])
            ->assertStatus(200)
            ->assertJsonStructure([
                "meta" => [
                    "user" => [
                        "name",
                        "email"
                    ]
                ],
                "data" => [
                    "type",
                    "jwt"
                ]
            ]);

        // dd($response);
    }

    public function testFailedLogin()
    {
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        //add token to db
        factory(UserPasswordlessApi::class)
            ->create([
                'email' => $email,
                'code' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $this->json('POST', '/api/login/passwordless', [
            'email' => $email,
            //'code' => $code
        ], [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "code"
                    ]
                ]
            ]);
        $this->json('POST', '/api/login/passwordless', [
            //'email' => $email,
            'code' => $code
        ], [])
            ->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_21",
                        "title" => "Required field is missing.",
                        "source" => "email"
                    ]
                ]
            ]);

        $this->json('POST', '/api/login/passwordless', [
            //  'email' => $email,
            //'code' => $code
        ], [])
            ->assertStatus(400)
            ->assertExactJson(["errors" => [
                [
                    "code" => "ec5_21",
                    "title" => "Required field is missing.",
                    "source" => "email"
                ],
                [
                    "code" => "ec5_21",
                    "title" => "Required field is missing.",
                    "source" => "code"
                ]
            ]]);

        $this->json('POST', '/api/login/passwordless', [
            'email' => $email,
            'code' => 'wrong'
        ], [])->assertStatus(400)
            ->assertExactJson([
                "errors" => [
                    [
                        "code" => "The code must be 6 characters.",
                        "title" => "The code must be 6 characters.",
                        "source" => "code"
                    ],
                    [
                        "code" => "ec5_87",
                        "title" => "Format not matched.",
                        "source" => "code"
                    ]
                ]
            ]);

        //brute force attack!          
        for ($i = 0; $i < 50; $i++) {
            $this->json('POST', '/api/login/passwordless', [
                'email' => $email,
                'code' => strval(Generators::randomNumber(6, 1))
            ], [])
                ->assertStatus(400)
                ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_378",
                            "title" => "Code invalid!",
                            "source" => "passwordless-api"
                        ]
                    ]
                ]);
        }
    }
}
