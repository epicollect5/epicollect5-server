<?php

namespace ec5\Http\Controllers\Api\Auth;

use Auth;
use ec5\Libraries\Auth\Jwt\JwtUserProvider;
use ec5\Services\User\UserService;
use Log;
use Response;

class LocalController extends AuthController
{
    /*
    |--------------------------------------------------------------------------
    | Api Local Authentication Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the authentication of local users via the api.
    | Returns a generated JWT token
    | Local users have a password
    */

    public function __construct(JwtUserProvider $provider)
    {
        parent::__construct($provider);
    }

    /**
     * Handle a login request to the application.
     */
    public function authenticate()
    {
        $credentials = request()->only('username', 'password');

        //do we accept local auth?
        if (in_array('local', $this->authMethods) || $this->isAuthApiLocalEnabled) {

            //check if email is whitelisted
            if (!UserService::isAuthenticationDomainAllowed($credentials['username'])) {
                Log::error('Email not whitelisted', ['email' => $credentials['username']]);
                return Response::apiErrorCode(400, ['passwordless-request-code' => ['ec5_266']]);
            }

            // Verify user, without setting cookie
            if (Auth::guard()->attempt([
                'email' => $credentials['username'],
                'password' => $credentials['password'],
                'state' => config('epicollect.strings.user_state.active')
            ])) {
                // Jwt
                $data = Auth::guard()->authorizationResponse();
                // User name in meta
                $meta = [
                    'user' => [
                        'name' => Auth::guard()->user()->name,
                        'email' => Auth::guard()->user()->email
                    ]
                ];
                // Return JWT response
                return Response::apiData($data, $meta);
            }
            // Log::error('Local Login failed: ' . $credentials['username']);
            $error['api-local-login'] = ['ec5_12'];
            return Response::apiErrorCode(404, $error);
        }

        // Auth method not allowed
        $error['api-local-login'] = ['ec5_55'];

        Log::error('Local Login not allowed: ' . $credentials['username']);
        return Response::apiErrorCode(400, $error);
    }
}
