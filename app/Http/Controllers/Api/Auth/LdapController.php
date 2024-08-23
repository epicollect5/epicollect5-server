<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Libraries\Jwt\JwtUserProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ec5\Libraries\Ldap\Ldap;
use Auth;
use Log;
use Response;
use Throwable;

class LdapController extends AuthController
{
    /*
    |--------------------------------------------------------------------------
    | Api LDAP Authentication Controller
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
     * Handle a ldap login api request to the application.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function authenticate(Request $request)
    {
        $credentials = $request->only('username', 'password');

        Log::info('LDAP Login: ' . $credentials['username']);

        // Check this auth method is allowed
        if (in_array('ldap', $this->authMethods)) {
            // Attempt to find the ldap user
            try {

                // If we can't connect
                if (Ldap::hasErrors()) {
                    return Response::apiErrorCode(404, Ldap::errors());
                }

                $ldapUser = Ldap::retrieveByCredentials($credentials);

                // Check ldap user exists
                if (!$ldapUser) {
                    Log::error('LDAP Login failed - cant find ldap user: ' . $credentials['username']);
                    $error['api/login'] = ['ec5_33'];
                    return Response::apiErrorCode(404, $error);
                }

                $user = $this->provider->findUserByEmail($ldapUser->getAuthIdentifier());

                // Check user exists
                if (!$user || $user->state != config('epicollect.strings.user_state.active')) {
                    Log::error('LDAP Login failed - cant find ec5 user: ' . $credentials['username']);
                    $error['api/login'] = ['ec5_33'];
                    return Response::apiErrorCode(404, $error);
                }

                // Log user in
                Auth::guard()->login($user);
                // JWT
                $data = Auth::guard()->authorizationResponse();
                // User name in meta
                $meta = [
                    'user' => [
                        'name' => Auth::guard()->user()->name,
                        'email' => Auth::guard()->user()->email
                    ]
                ];
                // Return JWT response
                Log::info('LDAP Login successful: ' . $credentials['username']);
                return Response::apiData($data, $meta);
            } catch (Throwable $e) {
                // If any exceptions, return error response: could not authenticate
                Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            }

            Log::error('LDAP Login failed: ' . $credentials['username']);
            $error['api/login'] = ['ec5_33'];
            return Response::apiErrorCode(404, $error);
        }

        Log::error('LDAP Login not allowed: ' . $credentials['username']);
        // Auth method not allowed
        $error['api/login'] = ['ec5_55'];
        return Response::apiErrorCode(400, $error);
    }
}
