<?php

namespace Tests\Exceptions;

use ec5\Exceptions\Handler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Tests\TestCase;

class HandlerTest extends TestCase
{
    public function test_it_preserves_throttle_headers_when_rendering_rate_limit_errors(): void
    {
        $handler = app(Handler::class);
        $request = Request::create('/api/internal/project/example', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $exception = new ThrottleRequestsException(
            'Too many requests',
            null,
            [
                'Retry-After' => '120',
                'X-RateLimit-Limit' => '60',
            ]
        );

        $response = $handler->render($request, $exception);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('120', $response->headers->get('Retry-After'));
        $this->assertSame('60', $response->headers->get('X-RateLimit-Limit'));
    }
}
