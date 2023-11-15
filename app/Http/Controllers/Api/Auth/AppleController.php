<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Validation\Auth\RulePasswordlessApiLogin;
use ec5\Models\Eloquent\UserPasswordlessApi;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Models\Eloquent\User;
use ec5\Models\Eloquent\UserProvider;
use ec5\Libraries\Jwt\JwtUserProvider;
use ec5\Traits\Auth\AppleJWTHandler;
use Illuminate\Http\Request;
use Exception;
use Config;
use Auth;
use Log;

class AppleController extends AuthController
{
    /*
    |--------------------------------------------------------------------------
    | Api Authentication Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the authentication of Apple users via the api.
    | Returns a generated JWT token
    |
    */

    use AppleJWTHandler;

    public function __construct(JwtUserProvider $provider)
    {
        parent::__construct($provider);
    }

    /**
     * Login Apple users
     */
    public function authUser(Request $request, ApiResponse $apiResponse)
    {
        //todo: validate request
        //check if local logins are enabled
        $appleUser = null;

        try {
            //get Apple jwt
            $params = $request->all();
            Log::error('Apple $params', ['$params' => $params]);
            $identityToken = $params['identityToken'];

            //validate Apple jwt
            $parsed_id_token = $this->parseIdentityToken($identityToken);
            Log::error('Apple parsed id token', ['token' => $parsed_id_token]);
            if (!$parsed_id_token) {
                $error['api-login-apple'] = ['ec5_382'];
                return $apiResponse->errorResponse(400, $error);
            }

            //catching error when email and email_verified are missing from payload
            //happens for instance when users change their Apple ID email
            if (!isset($parsed_id_token['email_verified'])) {
                //return api error
                $error['api-login-apple'] = ['ec5_386'];
                return $apiResponse->errorResponse(400, $error);
            }

            if ($parsed_id_token['email_verified'] !== 'true') {
                //return api error
                $error['api-login-apple'] = ['ec5_382'];
                return $apiResponse->errorResponse(400, $error);
            }

            //get Apple user email, always sent in the token
            $email = $parsed_id_token['email'];
            //look for the user
            $userModel = new User();
            $user = $userModel->where('email', $email)->first();

            //let's see if we have a firstName object
            //Apple sends this only on fist authentication attempt
            try {
                $appleUser = $params['user']; //decode to array by passing "true"
                $appleUserFirstName = $appleUser['givenName'];
                if (empty($appleUserFirstName)) {
                    $appleUserFirstName = config('ec5Strings.user_placeholder.apple_first_name');
                }

                $appleUserLastName = $appleUser['familyName'];
                if (empty($appleUserLastName)) {
                    $appleUserLastName = config('ec5Strings.user_placeholder.apple_last_name');
                }
            } catch (Exception $e) {
                Log::error('Apple user object exception', ['exception' => $e->getMessage()]);
                //if no user name found, default to Apple User
                $appleUserFirstName = config('ec5Strings.user_placeholder.apple_first_name');
                $appleUserLastName = config('ec5Strings.user_placeholder.apple_last_name');
            }

            if (!$user) {

                /**
                 * If no Epicollect5 user for this email is not found,
                 * create the user as new with the Apple provider
                 * and return it
                 */
                $user = $userModel->createAppleUser($appleUserFirstName, $appleUserLastName, $email);
            }

            //if the user is disabled, kick him out
            if ($user->state === Config::get('ec5Strings.user_state.disabled')) {
                $error['api-login-apple'] = ['ec5_212'];
                return $apiResponse->errorResponse(400, $error);
            }
            /**
             * if we have a user with unverified state,
             * it means the user was added to a project
             * before having an account.
             *
             * Update the current user as active
             * and add the Apple provider
             *
             * the user gets verified via Apple
             */
            if ($user->state === Config::get('ec5Strings.user_state.unverified')) {
                if (!$userModel->updateAppleUser($appleUserFirstName, $appleUserLastName, $email, true)) {

                    $error['api-login-apple'] = ['ec5_45'];
                    return $apiResponse->errorResponse(400, $error);
                }

                //refresh current instance of user details since it was verified correctly
                $user->state = Config::get('ec5Strings.user_state.active');
                $user->name = $appleUserFirstName;
            }
            /**
             * User was found and active, does this user have an Apple provider?
             */
            if ($user->state === Config::get('ec5Strings.user_state.active')) {

                $userProviders = UserProvider::where('email', $email)
                    ->pluck('provider')->toArray();

                if (!in_array($this->appleProviderLabel, $userProviders)) {
                    /**
                     * if the user is active but the Apple provider is not found,
                     * this user created an account with another provider (apple or passwordless)
                     *
                     * Ask the user to connect the Apple account from the profile page
                     * for verification
                     */

                    //if the user is local and local auth is enabled, user must povide password
                    if ($this->isAuthApiLocalEnabled) {
                        if (in_array($this->localProviderLabel, $userProviders)) {
                            $error['api-login-apple'] = ['ec5_390'];
                            return $apiResponse->errorResponse(400, $error);
                        }
                    }

                    $error['api-login-apple'] = ['ec5_384'];
                    return $apiResponse->errorResponse(400, $error);
                }

                //Update exiting user name and last name when a user object is received
                if ($appleUser) {

                    //update user name and last name only when they are still placeholders
                    if ($user->name === config('ec5Strings.user_placeholder.apple_first_name')) {
                        if (!$userModel->updateAppleUser($appleUserFirstName, $appleUserLastName, $email, false)) {
                            $error['api-login-apple'] = ['ec5_45'];
                            return $apiResponse->errorResponse(400, $error);
                        }
                    }
                    if ($user->name === config('ec5Strings.user_placeholder.passwordless_first_name')) {
                        if (!$userModel->updateAppleUser($appleUserFirstName, $appleUserLastName, $email, false)) {
                            $error['api-login-apple'] = ['ec5_45'];
                            return $apiResponse->errorResponse(400, $error);
                        }
                    }
                }
            }

            /**
             * external_api routes use the global pattern
             * https://laravel.com/docs/5.4/routing#parameters-global-constraints
             * so they all get the jwt guard when calling guard() without parameters.
             *
             * Patterns are defined in RouteServiceProvider.php
             *
             * Guards and Providers are defined in config/auth.php
             */

            // Log user in
            Auth::guard()->login($user);
            // JWT
            $apiResponse->setData(Auth::guard()->authorizationResponse());
            // User name, email in meta
            $apiResponse->setMeta([
                'user' => [
                    'name' => Auth::user()->fresh()->name,
                    'email' => Auth::user()->fresh()->email
                ]
            ]);
            // Return JWT response
            return $apiResponse->toJsonResponse(200, 0);
        } catch (Exception $e) {
            // If any exceptions, return error response: could not authenticate
            Log::error('Apple Login JWT Exception: ', [
                'exception' => $e
            ]);
            $error['api-login-apple'] = ['ec5_266'];
            return $apiResponse->errorResponse(400, $error);
        }
    }

    /**
     * This verifies an Apple User who already has an account (Google or passwordless)
     * If the code is valid, the apple provider is added
     * This is performed only the first time the user logs in with a new provider
     *
     * IMP:Local users are asked to enter the password when they login using a different provider
     * IMP:they are not verified here, local auth has its own verification controller
     */
    public function verifyUserEmail(Request $request, ApiResponse $apiResponse, RulePasswordlessApiLogin $validator)
    {
        //validate request
        $inputs = $request->all();
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            return $apiResponse->errorResponse(400, $validator->errors());
        }

        $code = $inputs['code'];
        $email = $inputs['email'];

        //get token from db for comparison
        $userPasswordless = UserPasswordlessApi::where('email', $email)->first();

        //Does the email exists?
        if ($userPasswordless === null) {
            Log::error('Error validating passworless code', ['error' => 'Email does not exist']);
            return $apiResponse->errorResponse(400, ['api-login-apple' => ['ec5_378']]);
        }

        //check if the code is valid
        if (!$userPasswordless->isValidCode($code)) {
            Log::error('Error validating passworless code', ['error' => 'Code not valid']);
            return $apiResponse->errorResponse(400, ['api-login-apple' => ['ec5_378']]);
        }

        //code is valid, remove it
        $userPasswordless->delete();

        //find the existing user
        $user = User::where('email', $email)->first();
        if ($user === null) {
            //this should never happen, but no harm in checking
            return $apiResponse->errorResponse(400, ['api-login-apple' => ['ec5_34']]);
        }

        //add the apple provider so next time no verification is needed
        $userProvider = new UserProvider();
        $userProvider->email = $user->email;
        $userProvider->user_id = $user->id;
        $userProvider->provider = $this->appleProviderLabel;
        $userProvider->save();

        //update user details (if a user object is available)
        try {
            $appleUser = $inputs['user']; //decode to array by passing "true"
            $appleUserFirstName = $appleUser['givenName'];
            $appleUserLastName = $appleUser['familyName'];

            //update user name and last name only when they are still placeholders
            if ($user->name === config('ec5Strings.user_placeholder.apple_first_name')) {
                $user->name = $appleUserFirstName;
                $user->last_name = $appleUserLastName;
                $user->save();
            }
            if ($user->name === config('ec5Strings.user_placeholder.passwordless_first_name')) {
                $user->name = $appleUserFirstName;
                $user->last_name = $appleUserLastName;
                $user->save();
            }
        } catch (Exception $e) {
            Log::error('Apple user object exception', ['exception' => $e->getMessage()]);
        }
        // Log user in
        Auth::guard()->login($user);
        // JWT
        $apiResponse->setData(Auth::guard()->authorizationResponse());
        // User name, email in meta
        $apiResponse->setMeta([
            'user' => [
                'name' => Auth::user()->fresh()->name,
                'email' => Auth::user()->fresh()->email
            ]
        ]);
        // Return JWT response
        return $apiResponse->toJsonResponse(200, 0);
    }
}
