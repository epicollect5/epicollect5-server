<?php

namespace ec5\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;
use Symfony\Component\HttpFoundation\Cookie;
use Config;

class VerifyCsrfToken extends BaseVerifier
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
     * Add the CSRF token to the response cookies.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Http\Response $response
     * @return \Illuminate\Http\Response
     */
    protected function addCookieToResponse($request, $response)
    {
        $config = config('session');

        $response->headers->setCookie(
            new Cookie(
            // Set cookie expiry as 'lifetime' from session config file
                'XSRF-TOKEN', $request->session()->token(), time() + 60 * config('session.lifetime'),
                $config['path'], $config['domain'], $config['secure'], false
            )
        );

        return $response;
    }
}
