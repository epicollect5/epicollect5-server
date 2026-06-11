<?php

namespace ec5\Http\Middleware;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as BaseVerifier;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class PreventRequestForgery extends BaseVerifier
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'handle/apple',
        'profile/connect-apple-callback'
    ];

    /**
     *
     *  imp: to make it work like pre Laravel 7
     *   without this, X-CSRF token from Ajax post requests
     *   stops working
     */
    public static function serialized(): bool
    {
        return true;
    }

    /**
     * Add the CSRF token to the response cookies.
     *
     */
    protected function addCookieToResponse($request, $response): Response
    {
        if ($response instanceof Responsable) {
            $response = $response->toResponse($request);
        }

        $config = config('session');

        $response->headers->setCookie(
            Cookie::create('XSRF-TOKEN')
                ->withValue($request->session()->token())
                ->withExpires(time() + 60 * $config['lifetime'])
                ->withPath($config['path'])
                ->withDomain($config['domain'])
                ->withSecure($config['secure'])
                ->withHttpOnly(false)        // Must be false — JS needs to read it
                ->withSameSite('lax')        // Explicit, readable, not positional
        );

        return $response;
    }
}
