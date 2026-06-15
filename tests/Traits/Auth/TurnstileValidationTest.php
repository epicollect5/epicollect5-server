<?php

namespace Tests\Traits\Auth;

use ec5\Traits\Auth\TurnstileValidation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TurnstileValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $this->app->instance('request', $request);

        config()->set(
            'epicollect.setup.cloudflare_turnstile.verify_endpoint',
            'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        );
    }

    private function createValidator(): object
    {
        return new class () {
            use TurnstileValidation;
        };
    }

    public function test_it_returns_empty_array_on_successful_verification()
    {
        $endpoint = config('epicollect.setup.cloudflare_turnstile.verify_endpoint');

        Http::fake([
            $endpoint => Http::response([
                'success' => true,
                'challenge_ts' => '2024-01-01T00:00:00Z',
                'hostname' => 'example.com',
                'error-codes' => [],
            ], 200),
        ]);

        $errors = $this->createValidator()->getAnyTurnstileErrors('valid-token');

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function test_it_returns_ec5_380_when_response_is_not_an_array()
    {
        $endpoint = config('epicollect.setup.cloudflare_turnstile.verify_endpoint');

        Http::fake([
            $endpoint => Http::response('invalid json', 200),
        ]);

        $errors = $this->createValidator()->getAnyTurnstileErrors('token');

        $this->assertIsArray($errors);
        $this->assertEquals(['captcha' => ['ec5_380']], $errors);
    }

    public function test_it_returns_validation_errors_when_fields_missing()
    {
        $endpoint = config('epicollect.setup.cloudflare_turnstile.verify_endpoint');

        Http::fake([
            $endpoint => Http::response([
                'success' => true,
            ], 200),
        ]);

        $errors = $this->createValidator()->getAnyTurnstileErrors('token');

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('challenge_ts', $errors);
        $this->assertArrayHasKey('hostname', $errors);
        $this->assertEquals(['ec5_380'], $errors['challenge_ts']);
        $this->assertEquals(['ec5_380'], $errors['hostname']);
    }

    public function test_it_returns_ec5_380_when_additional_checks_fail()
    {
        $endpoint = config('epicollect.setup.cloudflare_turnstile.verify_endpoint');

        Http::fake([
            $endpoint => Http::response([
                'success' => false,
                'challenge_ts' => '2024-01-01T00:00:00Z',
                'hostname' => 'example.com',
                'error-codes' => [],
            ], 200),
        ]);

        $errors = $this->createValidator()->getAnyTurnstileErrors('token');

        $this->assertIsArray($errors);
        $this->assertEquals(['captcha' => ['ec5_380']], $errors);
    }

    public function test_it_returns_ec5_103_on_connection_exception()
    {
        $endpoint = config('epicollect.setup.cloudflare_turnstile.verify_endpoint');

        Http::fake([
            $endpoint => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $errors = $this->createValidator()->getAnyTurnstileErrors('token');

        $this->assertIsArray($errors);
        $this->assertEquals(['captcha' => ['ec5_103']], $errors);
    }

    public function test_it_rethrows_non_connection_exceptions()
    {
        $endpoint = config('epicollect.setup.cloudflare_turnstile.verify_endpoint');

        Http::fake([
            $endpoint => function () {
                throw new RuntimeException('Unexpected error');
            },
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected error');

        $this->createValidator()->getAnyTurnstileErrors('token');
    }
}
