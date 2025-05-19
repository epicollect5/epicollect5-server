<?php

namespace Tests\Routes\Api\external;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessApi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VerifyAppleUserExternalTest extends TestCase
{
    /**
     * Test an authenticated user's routes
     * imp: avoid $this->actingAs($user, 'api_external');
     * imp: as that create a valid user object therefore bypassing
     * imp: jwt validation. We need to send a valid token per each request
     * imp: instead.
     */

    //to reset database after tests
    use DatabaseTransactions;

    protected $privateProjectSlug;
    protected $publicProjectSlug;
    protected $endpoint;

    public function setUp(): void
    {
        parent::setUp();
        $this->privateProjectSlug = 'ec5-private';
        $this->publicProjectSlug = 'ec5-public';
        $this->endpoint = 'api/login/verify-apple';
    }

    public function testValidVerification()
    {
        //create test user
        $user = factory(User::class)->create();
        $email = $user->email;
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessApi::class)
            ->create([
                'email' => $email,
                'code' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $this->json('POST', $this->endpoint, [
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
    }

    public function testInvalidVerification()
    {
        $user = factory(User::class)->create();
        $email = $user->email;
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        //add token to db
        factory(UserPasswordlessApi::class)
            ->create([
                'email' => $email,
                'code' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $this->json('POST', $this->endpoint, [
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
        $this->json('POST', $this->endpoint, [
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

        $this->json('POST', $this->endpoint, [
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

        $this->json('POST', $this->endpoint, [
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
            $this->json('POST', $this->endpoint, [
                'email' => $email,
                'code' => strval(Generators::randomNumber(6, 1))
            ], [])
                ->assertStatus(400)
                ->assertExactJson([
                    "errors" => [
                        [
                            "code" => "ec5_378",
                            "title" => "Code invalid!",
                            "source" => "api-login-apple"
                        ]
                    ]
                ]);
        }
    }
}
