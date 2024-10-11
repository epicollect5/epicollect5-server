<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use ec5\Http\Validation\Auth\RulePasswordlessApiLogin;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use ec5\Services\User\UserService;
use ec5\Traits\Auth\AppleJWTHandler;
use ec5\Traits\Auth\AppleUserUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Log;
use Throwable;

class AppleController extends AuthController
{
    use AppleJWTHandler;
    use AppleUserUpdater;

    /*
    |--------------------------------------------------------------------------
    | Apple Sign In
    |--------------------------------------------------------------------------
    |
    | This controller handles the authentication of Apple users to the web app.
    |
    */

    public function __construct()
    {
        parent::__construct();
    }

    public function handleAppleCallback(Request $request)
    {
        $nonce = session('nonce');
        $appleUser = null;

        //check if local logins are enabled
        $isLocalAuthEnabled = in_array($this->localProviderLabel, $this->authMethods, true);

        //request parameters originally sent by Apple and posted here by ec5 front end
        // $params = $request->get('authorization');
        $params = $request->all();
        $token = $params['id_token'];
        $parsed_id_token = $this->parseIdentityToken($token);

        if (!$parsed_id_token) {
            return redirect()->route('login')->withErrors(['ec5_382']);
        }

        //catching error when email is missing from payload
        if (!isset($parsed_id_token['email'])) {
            Log::error(__METHOD__ . ' failed.', ['Apple Sign In' => 'email missing in payload']);
            return redirect()->route('login')->withErrors(['ec5_386']);
        }

        if ($parsed_id_token['nonce'] === $nonce) {
            //get Apple user email, always sent in the token
            $email = $parsed_id_token['email'];
            //look for the user
            $userModel = new User();
            $user = $userModel->where('email', $email)->first();

            //let's see if we have a user object
            //Apple sends this only on fist authentication attempt
            try {
                $appleUser = json_decode($params['user'], true); //decode to array by passing "true"
                $appleUserFirstName = $appleUser['name']['firstName'];
                $appleUserLastName = $appleUser['name']['lastName'];
            } catch (Throwable $e) {
                Log::error('Apple user object exception', ['exception' => $e->getMessage()]);
                //if no user name found, default to Apple User
                $appleUserFirstName = config('epicollect.mappings.user_placeholder.apple_first_name');
                $appleUserLastName = config('epicollect.mappings.user_placeholder.apple_last_name');
            }

            if (!$user) {

                /**
                 * If no Epicollect5 user for this email is not found,
                 * create the user as new with the Apple provider
                 * and return it
                 */
                $user = UserService::createAppleUser($appleUserFirstName, $appleUserLastName, $email);
            }

            //if the user is disabled, kick him out
            if ($user->state === config('epicollect.strings.user_state.disabled')) {
                return redirect()->route('login')->withErrors(['ec5_212']);
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
            if ($user->state === config('epicollect.strings.user_state.unverified')) {
                if (!UserService::updateAppleUser($appleUserFirstName, $appleUserLastName, $email, true)) {

                    return redirect()->route('login')->withErrors(['ec5_45']);
                }

                //set user as active since it was verified correctly
                $user->state = config('epicollect.strings.user_state.active');
            }

            /**
             * User was found and active, does this user have an Apple provider?
             */
            if ($user->state === config('epicollect.strings.user_state.active')) {

                $userProviders = UserProvider::where('email', $email)
                    ->pluck('provider')->toArray();

                //if the user is local redirect to admin/staff login
                if (in_array($this->localProviderLabel, $userProviders)) {
                    switch ($user->server_role) {

                        case config('epicollect.strings.server_roles.superadmin'):
                        case config('epicollect.strings.server_roles.admin'):
                            return redirect()->route('login-admin')->withErrors(['ec5_384']);
                        default:
                            if ($isLocalAuthEnabled) {
                                //redirect to staff login
                                return redirect()->route('login-staff')->withErrors(['ec5_384']);
                            } else {
                                //redirect to public login asking login via email
                                return redirect()->route('login')->withErrors(['ec5_391']);
                            }
                    }
                }

                if (!in_array($this->appleProviderLabel, $userProviders)) {
                    /**
                     * if the user is active but the Apple provider is not found,
                     * this user created an account with another provider (apple or passwordless)
                     *
                     * Ask the user to connect the Apple account from the profile page
                     * for verification
                     */

                    //redirect to code confirm page
                    return redirect()->route('verification-code')->with(
                        [
                            'email' => $user->email,
                            'provider' => $this->appleProviderLabel,
                            'name' => $appleUserFirstName,
                            'last_name' => $appleUserLastName
                        ]
                    );
                }

                //Update exiting user name and last name when a user object is received
                if ($appleUser) {
                    if (!UserService::updateAppleUser($appleUserFirstName, $appleUserLastName, $email, false)) {
                        return redirect()->route('login')->withErrors(['ec5_45']);
                    }
                }
            }
            //Login user
            session()->forget('nonce');
            Auth::login($user, false);
            $request->session()->regenerate();

            return redirect()->route('my-projects');
        }

        //we get here when there is any validation error
        Log::error(__METHOD__ . ' failed.', ['$parsed_id_token' => $parsed_id_token]);
        return redirect()->route('login')->withErrors(['ec5_386']);
    }

    /**
     * This verifies an Apple User who already has an account (Google)
     * If the code is valid, the Apple provider is added
     * This is performed only the first time the user logs in with a new provider
     *
     * IMP:Local users are asked to enter the password when they login using a different provider
     * IMP:they are not verified here, local auth has its own verification controller
     */
    public function verify(Request $request, RulePasswordlessApiLogin $validator)
    {
        //validate request
        $inputs = $request->all();
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            return redirect()->route('verification-code')->withErrors($validator->errors());
        }

        $code = $inputs['code'];
        $email = $inputs['email'];
        $provider = $this->appleProviderLabel;

        $result = $this->validateAppleOrGoogleUserWeb($email, $code, $provider);
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        $user = $result;

        //try to update the Apple user detail (name, surname)
        $this->updateAppleUserDetails($user, $inputs);

        //Login user at this point
        Auth::login($user, false);
        return $this->sendLoginResponse($request);
    }
}
