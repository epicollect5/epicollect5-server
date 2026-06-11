<?php

namespace Tests\Http\Middleware;

use ec5\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class PreventRequestForgeryTest extends TestCase
{
    public function test_serialized_returns_true(): void
    {
        $this->assertTrue(PreventRequestForgery::serialized());
    }

    public function test_xsrf_token_cookie_has_same_site_lax(): void
    {
        $cookie = $this->getXsrfCookieFromMiddleware();

        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_xsrf_token_cookie_is_not_http_only(): void
    {
        $cookie = $this->getXsrfCookieFromMiddleware();

        $this->assertFalse($cookie->isHttpOnly());
    }

    public function test_xsrf_token_cookie_contains_session_token(): void
    {
        $cookie = $this->getXsrfCookieFromMiddleware();

        $this->assertSame(session()->token(), $cookie->getValue());
    }

    public function test_xsrf_token_cookie_applies_session_config(): void
    {
        $cookie = $this->getXsrfCookieFromMiddleware();

        $this->assertSame(config('session.path'), $cookie->getPath());
        $this->assertSame(config('session.domain'), $cookie->getDomain());
        $this->assertSame(config('session.secure'), $cookie->isSecure());
    }

    public function test_xsrf_token_cookie_expires_after_session_lifetime(): void
    {
        $cookie = $this->getXsrfCookieFromMiddleware();

        $expectedExpiry = time() + 60 * config('session.lifetime');

        $this->assertEqualsWithDelta($expectedExpiry, $cookie->getExpiresTime(), 2);
    }

    public function test_apple_callback_path_bypasses_csrf_verification(): void
    {
        $response = $this->post('handle/apple');

        $this->assertNotEquals(419, $response->getStatusCode());
    }

    public function test_connect_apple_callback_path_bypasses_csrf_verification(): void
    {
        $response = $this->post('profile/connect-apple-callback');

        $this->assertNotEquals(419, $response->getStatusCode());
    }

    private function getXsrfCookieFromMiddleware(): Cookie
    {
        $middleware = new PreventRequestForgery(
            app(\Illuminate\Contracts\Foundation\Application::class),
            app(\Illuminate\Contracts\Encryption\Encrypter::class)
        );

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, function () {
            return new Response('ok');
        });

        $cookies = $response->headers->getCookies();

        $xsrfCookie = null;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN') {
                $xsrfCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($xsrfCookie, 'XSRF-TOKEN cookie was not set on the response');

        return $xsrfCookie;
    }
}
