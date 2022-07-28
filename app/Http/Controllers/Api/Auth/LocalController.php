<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Libraries\Jwt\JwtUserProvider;
use Illuminate\Http\Request;
use Config;
use Auth;
use Log;

class LocalController extends AuthController
{
    /*
    |--------------------------------------------------------------------------
    | Api Local Authentication Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the authentication of local users via the api.
    | Returns a generated JWT token
    | LOcal users have a password
    */

    public function __construct(JwtUserProvider $provider)
    {
        parent::__construct($provider);
    }

    /**
     * Handle a login request to the application.
     *
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function authenticate(Request $request, ApiResponse $apiResponse)
    {
        $credentials = $request->only('username', 'password');

        //do we accept local auth?
        if (in_array('local', $this->authMethods) || $this->isAuthApiLocalEnabled) {

            // Verify user, without setting cookie
            if (Auth::guard()->attempt([
                'email' => $credentials['username'],
                'password' => $credentials['password'],
                'state' => Config::get('ec5Strings.user_state.active')
            ])) {
                // Log::info('Local Login successful: ' . $credentials['username']);
                // Jwt
                $apiResponse->setData(Auth::guard()->authorizationResponse());
                // User name in meta
                $apiResponse->setMeta([
                    'user' => [
                        'name' => Auth::guard()->user()->name,
                        'email' => Auth::guard()->user()->email
                    ]
                ]);
                // Return JWT response
                return $apiResponse->toJsonResponse(200, 0);
            }
            // Log::error('Local Login failed: ' . $credentials['username']);
            $error['api-local-login'] = ['ec5_12'];
            return $apiResponse->errorResponse(404, $error);
        }

        // Auth method not allowed
        $error['api-local-login'] = ['ec5_55'];

        Log::error('Local Login not allowed: ' . $credentials['username']);
        return $apiResponse->errorResponse(400, $error);
    }
}
