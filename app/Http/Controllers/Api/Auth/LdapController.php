<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Libraries\Jwt\JwtUserProvider;
use Illuminate\Http\Request;
use ec5\Libraries\Ldap\Ldap;
use Exception;
use Config;
use Auth;
use Log;

class LdapController extends AuthController
{
    /*
    |--------------------------------------------------------------------------
    | Api LDAP Authentication Controller
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
     * Handle an ldap login api request to the application.
     *
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function authenticate(Request $request, ApiResponse $apiResponse)
    {
        $credentials = $request->only('username', 'password');

        Log::info('LDAP Login: ' . $credentials['username']);

        // Check this auth method is allowed
        if (in_array('ldap', $this->authMethods)) {
            // Attempt to find the ldap user
            try {

                // If we can't connect
                if (Ldap::hasErrors()) {
                    return $apiResponse->errorResponse(404, Ldap::errors());
                }

                $ldapUser = Ldap::retrieveByCredentials($credentials);

                // Check ldap user exists
                if (!$ldapUser) {
                    Log::error('LDAP Login failed - cant find ldap user: ' . $credentials['username']);
                    $error['api/login'] = ['ec5_33'];
                    return $apiResponse->errorResponse(404, $error);
                }

                $user = $this->provider->findUserByEmail($ldapUser->getAuthIdentifier());

                // Check user exists
                if (!$user || $user->state != Config::get('ec5Strings.user_state.active')) {
                    Log::error('LDAP Login failed - cant find ec5 user: ' . $credentials['username']);
                    $error['api/login'] = ['ec5_33'];
                    return $apiResponse->errorResponse(404, $error);
                }

                // Log user in
                Auth::guard()->login($user);
                // JWT
                $apiResponse->setData(Auth::guard()->authorizationResponse());
                // User name in meta
                $apiResponse->setMeta([
                    'user' => [
                        'name' => Auth::guard()->user()->name,
                        'email' => Auth::guard()->user()->email
                    ]
                ]);
                // Return JWT response
                Log::info('LDAP Login successful: ' . $credentials['username']);
                return $apiResponse->toJsonResponse(200, 0);
            } catch (Exception $e) {
                // If any exceptions, return error response: could not authenticate
                Log::error('LDAP Login failed - exception thrown: ', null, [json_encode($e)]);
            }

            Log::error('LDAP Login failed: ' . $credentials['username']);
            $error['api/login'] = ['ec5_33'];
            return $apiResponse->errorResponse(404, $error);
        }

        Log::error('LDAP Login not allowed: ' . $credentials['username']);
        // Auth method not allowed
        $error['api/login'] = ['ec5_55'];
        return $apiResponse->errorResponse(400, $error);
    }
}
