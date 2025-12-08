<?php

namespace Tests\Http\Controllers\Api\Auth;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Mail\UserPasswordlessApiMail;
use ec5\Models\User\UserPasswordlessApi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use JetBrains\PhpStorm\NoReturn;
use Tests\TestCase;

class PasswordlessControllerApiTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test an authenticated user's routes
     * imp: avoid $this->actingAs($user, 'api_external');
     * imp: as that create a valid user object therefore bypassing
     * imp: jwt validation. We need to send a valid token per each request
     * imp: instead.
     */
    public function setup(): void
    {
        parent::setUp();
    }

    public function test_send_code_api()
    {
        $email = config('testing.MANAGER_EMAIL');
        Mail::fake();

        $response = $this->post(route('passwordless-token-api'), [
            'email' => $email
        ]);

        $response->assertStatus(200);
        $response->assertExactJson([
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

    public function test_send_code_api_but_domain_not_allowed()
    {
        config()->set(['auth.auth_allowed_domains' => ['example.com']]);
        $email = config('testing.MANAGER_EMAIL');
        Mail::fake();

        $response = $this->post(route('passwordless-token-api'), [
            'email' => $email
        ]);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_266',
                    'title' => 'Auth user exception. Please contact support.',
                    'source' => 'passwordless-request-code',
                ],
            ],
        ]);
        // Assert a message was sent to the given users...
        Mail::assertNotSent(UserPasswordlessApiMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }


    public function test_missing_email()
    {
        //send a code to user for authentication
        Mail::fake();
        $response = $this->post(route('passwordless-token-api'), [
        ]);
        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_21',
                    'title' => 'Required field is missing.',
                    'source' => 'email',
                ],
            ],
        ]);
    }

    #[NoReturn]
    public function test_login_api()
    {
        config()->set('auth.auth_allowed_domains', []);
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessApi::class)
            ->create([
                'email' => $email,
                'code' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $response = $this->post(route('passwordless-auth-api'), [
            'email' => $email,
            'code' => $code
        ], []);

        //should redirect to intended url
        $response->assertStatus(200);

        //should send jwt and email in the response
        $this->assertNotEmpty($response->original['data']['jwt']);
        $this->assertEquals('jwt', $response->original['data']['type']);
        $this->assertEquals($email, $response->original['meta']['user']['email']);
    }

    public function test_login_disallowed_domain()
    {
        config()->set('auth.auth_allowed_domains', ['example.com']);
        $email = config('testing.MANAGER_EMAIL');
        $tokenExpiresAt = config('testing.PASSWORDLESS_TOKEN_EXPIRES_IN', 300);
        $code = Generators::randomNumber(6, 1);

        factory(UserPasswordlessApi::class)
            ->create([
                'email' => $email,
                'code' => bcrypt($code, ['rounds' => config('testing.BCRYPT_ROUNDS')]),
                'expires_at' => Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString()
            ]);

        $response = $this->post(route('passwordless-auth-api'), [
            'email' => $email,
            'code' => $code
        ], []);

        //user should not be logged in
        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_266',
                    'title' => 'Auth user exception. Please contact support.',
                    'source' => 'passwordless-api',
                ],
            ],
        ]);
    }

    public function test_failed_login()
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

        $response = $this->post(route('passwordless-auth-api'), [
            'email' => 'not-an-email',
            'code' => $code
        ], []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_42',
                    'title' => 'Email address is not correct.',
                    'source' => 'email',
                ]
            ]
        ]);



        $response = $this->post(route('passwordless-auth-api'), [
            'email' => $email,
            //'code' => $code
        ], []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_21',
                    'title' => 'Required field is missing.',
                    'source' => 'code',
                ]
            ]
        ]);

        $response = $this->post(route('passwordless-auth-api'), [
            //'email' => $email,
            'code' => $code
        ], []);


        $response->assertStatus(400);

        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_21',
                    'title' => 'Required field is missing.',
                    'source' => 'email',
                ]
            ]
        ]);

        $response = $this->post(route('passwordless-auth-api'), [
            //  'email' => $email,
            //'code' => $code
        ], []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_21',
                    'title' => 'Required field is missing.',
                    'source' => 'email',
                ],
                [
                    'code' => 'ec5_21',
                    'title' => 'Required field is missing.',
                    'source' => 'code',
                ]
            ]]);

        // First invalid attempt
        $response = $this->post(route('passwordless-auth-api'), [
            'email' => $email,
            'code' => Generators::randomNumber(6, 1)
        ], []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_378',
                    'title' => 'Code invalid!',
                    'source' => 'passwordless-api',
                ],
            ]
        ]);


        //Second invalid attempt
        $response = $this->post(route('passwordless-auth-api'), [
            'email' => $email,
            'code' => Generators::randomNumber(6, 1)
        ], []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_378',
                    'title' => 'Code invalid!',
                    'source' => 'passwordless-api',
                ],
            ]
        ]);

        //Third invalid attempt, redirect back to login
        $response = $this->post(route('passwordless-auth-api'), [
            'email' => $email,
            'code' => Generators::randomNumber(6, 1)
        ], []);

        $response->assertStatus(400);
        $response->assertExactJson([
            'errors' => [
                [
                    'code' => 'ec5_378',
                    'title' => 'Code invalid!',
                    'source' => 'passwordless-api',
                ],
            ]
        ]);
    }
}
