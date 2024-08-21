<?php

namespace ec5\Http\Controllers\Api\Auth;

use Auth;
use ec5\Http\Validation\Auth\RulePasswordlessApiLogin;
use ec5\Libraries\Jwt\JwtUserProvider;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessApi;
use ec5\Models\User\UserProvider;
use ec5\Services\User\UserService;
use ec5\Traits\Auth\GoogleUserUpdater;
use Exception;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Log;
use Response;

class GoogleController extends AuthController
{
    use GoogleUserUpdater;

    public function __construct(JwtUserProvider $provider)
    {
        parent::__construct($provider);
    }

    /**
     * Accepts access code and creates google social user
     * Returning jwt in response header
     */
    public function authGoogleUser()
    {
        // Check this auth method is allowed
        if (in_array('google', $this->authMethods)) {
            $provider = $this->googleProviderLabel;
            $providerLocal = $this->localProviderLabel;
            // Attempt to find the google user
            try {
                $providerKey = config('services.google_api');

                // We want stateless here, as using jwt
                // Build the custom provider driver based on google driver and load the user
                /**
                 * IMP: the Socialite call needs a code and grant_tyoe from the app request like:
                 *  code: code,
                 *  grant_type: 'authorization_code'
                 *  which is provided by the mobile app post request.
                 *  Without that, it would fail.
                 *  The $googleUser object is the same we get from the web
                 *  so we can use the same model methods
                 */
                $googleUser = Socialite::buildProvider('Laravel\Socialite\Two\GoogleProvider', $providerKey)->stateless()->user();


                // Check user exists in Epicollect5 system and is active
                $userModel = new User();
                $user = $userModel->where('email', $googleUser->email)->first();

                /**
                 * If no Epicollect5 user for this email is not found,
                 * create the user as new with the Google provider
                 * and return it
                 */
                if (!$user) {
                    $user = UserService::createGoogleUser($googleUser);
                    if (!$user) {
                        $error['api-login-google'] = ['ec5_376'];
                        return Response::apiErrorCode(400, $error);
                    }
                }

                //if the user is disabled, kick him out
                if ($user->state === config('epicollect.strings.user_state.disabled')) {
                    Log::error('Google Login failed - user not active anymore: ' . $googleUser->email);
                    $error['api-login-google'] = ['ec5_32'];
                    return Response::apiErrorCode(400, $error);
                }

                /**
                 * if we have a user with unverified state,
                 * it means the user was added to a project
                 * before having an account.
                 *
                 * Update the current user as active
                 * and add the Google provider
                 *
                 * the user gets verified via Google
                 */
                if ($user->state === config('epicollect.strings.user_state.unverified')) {
                    if (!UserService::updateGoogleUser($googleUser)) {
                        $error['api-login-google'] = ['ec5_45'];
                        return Response::apiErrorCode(400, $error);
                    }
                    //set user as active since it was verified correctly
                    $user->state = config('epicollect.strings.user_state.active');
                }

                /**
                 * User was found and active, does this user have a Google provider?
                 */
                if ($user->state === config('epicollect.strings.user_state.active')) {

                    $userProviders = UserProvider::where('email', $googleUser->email)
                        ->pluck('provider')->toArray();

                    //if the user has a local provider, redirect to admin or staff login
                    //based on user server role
                    if (in_array($providerLocal, $userProviders)) {

                        switch ($user->server_role) {

                            //admins must enter password on the mobile app
                            case config('epicollect.strings.server_roles.superadmin'):
                            case config('epicollect.strings.server_roles.admin'):
                                $error['api-login-google'] = ['ec5_390'];
                                return Response::apiErrorCode(400, $error);
                                break;
                            default:
                                if ($this->isAuthApiLocalEnabled) {
                                    //staff must enter password on the app
                                    $error['api-login-google'] = ['ec5_390'];
                                    return Response::apiErrorCode(400, $error);
                                } else {
                                    //public login where Local users can only use the email to login
                                    $error['api-login-google'] = ['ec5_383'];
                                    return Response::apiErrorCode(400, $error);
                                }
                        }
                    }

                    if (!in_array($provider, $userProviders)) {
                        /**
                         * if the user is active but the Google provider is not found,
                         * this user created an account with another provider (apple or passwordless)
                         *
                         * Ask the user to verify account with 6 digits code
                         *
                         */

                        $error['api-login-google'] = ['ec5_383'];
                        return Response::apiErrorCode(400, $error);
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

                    //we always update user details just in case the google account was updated 
                    if (!UserService::updateGoogleUserDetails($googleUser)) {
                        //well, details not updated is not a show stopping error, just log it
                        Log::error('Could not update Google User details');
                    }

                    // Log user in
                    Auth::guard()->login($user);
                    // JWT
                    $data = Auth::guard()->authorizationResponse();
                    // User name, email in meta
                    $meta = [
                        'user' => [
                            'name' => Auth::user()->fresh()->name,
                            'email' => Auth::user()->fresh()->email
                        ]
                    ];

                    // Return JWT response
                    return Response::apiData($data, $meta);
                }
            } catch (\Throwable $e) {
                // If any exceptions, return error response: could not authenticate
                Log::error('Google Login JWT Exception: ', [
                    'exception' => $e
                ]);
                $error['api-login-google'] = ['ec5_266'];
                return Response::apiErrorCode(400, $error);
            }
        }
        // Auth method not allowed
        Log::error('Google Login not allowed');
        $error['api-login-google'] = ['ec5_55'];
        return Response::apiErrorCode(400, $error);
    }

    /**
     * This verifies a Google User who already has an account (Apple)
     * If the code is valid, the Google provider is added
     * This is performed only the first time the user logs in with a new provider
     *
     * IMP:Local users are asked to enter the password when they login using a different provider
     * IMP:they are not verified here, local auth has its own verification controller
     */
    public function verifyUserEmail(Request $request, RulePasswordlessApiLogin $validator)
    {
        //validate request
        $params = $request->all();
        Log::error('Google $params', ['$params' => $params]);
        $validator->validate($params);
        if ($validator->hasErrors()) {
            return Response::apiErrorCode(400, $validator->errors());
        }

        $code = $params['code'];
        $email = $params['email'];

        //get token from db for comparison
        $userPasswordless = UserPasswordlessApi::where('email', $email)->first();

        //Does the email exists?
        if ($userPasswordless === null) {
            Log::error('Error validating passworless code', ['error' => 'Email does not exist']);
            return Response::apiErrorCode(400, ['api-login-google' => ['ec5_378']]);
        }

        //check if the code is valid
        if (!$userPasswordless->isValidCode($code)) {
            Log::error('Error validating passworless code', ['error' => 'Code not valid']);
            return Response::apiErrorCode(400, ['api-login-google' => ['ec5_378']]);
        }

        //code is valid, remove it
        $userPasswordless->delete();

        //find the existing user
        $user = User::where('email', $email)->first();
        if ($user === null) {
            //this should never happen, but no harm in checking
            return Response::apiErrorCode(400, ['api-login-google' => ['ec5_34']]);
        }

        //add the google provider so next time no verification is needed
        $userProvider = new UserProvider();
        $userProvider->email = $user->email;
        $userProvider->user_id = $user->id;
        $userProvider->provider = $this->googleProviderLabel;
        $userProvider->save();

        //try to update user details 
        try {
            $this->updateUserDetails($params, $user);
        } catch (\Throwable $e) {
            Log::error('Google user object exception', ['exception' => $e->getMessage()]);
        }
        // Log user in
        Auth::guard()->login($user);
        // JWT
        $data = Auth::guard()->authorizationResponse();
        // User name,email in meta
        $meta = [
            'user' => [
                'name' => Auth::user()->fresh()->name,
                'email' => Auth::user()->fresh()->email
            ]
        ];
        // Return JWT response
        return Response::apiData($data, $meta);
    }
}
