<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use ec5\Http\Validation\Auth\RulePasswordlessApiLogin;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use ec5\Services\User\UserService;
use ec5\Traits\Auth\GoogleUserUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Log;
use Throwable;

class GoogleController extends AuthController
{
    /*
    |--------------------------------------------------------------------------
    | Google Sign In
    |--------------------------------------------------------------------------
    |
    | This controller handles the authentication of Google users.
    |
    */

    use GoogleUserUpdater;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Function for redirecting to Google specific auth url
     */
    public function redirect()
    {
        $provider = $this->googleProviderLabel;
        // Check this auth method is allowed
        if (in_array($provider, $this->authMethods)) {
            // Retrieve provider config details
            $providerKey = config('services.' . $provider);

            if (empty($providerKey)) {
                return view('auth.login')->withErrors(['ec5_38']);
            }

            return Socialite::with($provider)->with(['prompt' => 'select_account'])->redirect();
        }
        // Auth method not allowed
        return view('auth.login')
            ->withErrors(['ec5_55']);
    }

    //Function for handling the Google specific auth callback
    public function handleCallback(Request $request)
    {
        //check if local logins are enabled
        $provider = $this->googleProviderLabel;

        try {
            // Find the Google user
            $googleUser = Socialite::with($provider)->user();

            //check if email is whitelisted
            if (!UserService::isAuthenticationDomainAllowed($googleUser->email)) {
                Log::error('Email not whitelisted', ['email' => $googleUser->email]);
                return redirect()->route('login')->withErrors(['ec5_266']);
            }

            // If we found a Google user
            if ($googleUser) {
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
                        return redirect()->route('login')->withErrors(['ec5_376']);
                    }
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
                 * and add the Google provider
                 *
                 * the user gets verified via Google
                 */
                if ($user->state === config('epicollect.strings.user_state.unverified')) {
                    if (!UserService::updateGoogleUser($googleUser)) {
                        return redirect()->route('login')->withErrors(['ec5_45']);
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

                    if (!in_array($provider, $userProviders)) {

                        /**
                         * if the user is active but the Google provider is not found,
                         * this user created an account with another provider (apple or passwordless)
                         *
                         * Ask the user to verify the Google account
                         * by sending a one off 6 digits code for verification
                         *
                         */

                        //redirect to code confirm page
                        return redirect()->route('verification-code')
                            ->with([
                                'email' => $user->email,
                                'provider' => $this->googleProviderLabel,
                                'name' => $googleUser['given_name'],
                                'last_name' => $googleUser['family_name']
                            ]);
                    }

                    //we always update user details just in case the Google account was updated
                    if (!UserService::updateGoogleUserDetails($googleUser)) {
                        //well, details not updated is not a show stopping error, just log it
                        Log::error('Could not update Google User details');
                    }

                    // Login user at this point
                    Auth::login($user, false);
                    return $this->sendLoginResponse($request);
                }
            }
        } catch (InvalidStateException $e) {
            Log::error('Google Login Web Exception: ', ['exception' => [$e]]);
            return redirect()->route('login')->withErrors(['ec5_213']);
        } catch (Throwable $e) {
            Log::error('Google Login Web Exception: ', ['exception' => $e->getMessage()]);
            return redirect()->route('login')->withErrors(['ec5_31']);
        }
        return redirect()->route('login')->withErrors(['ec5_32']);
    }

    /**
     * This verifies a Google User who already has an account with another provider (Apple)
     * If the code is valid, the Google provider is added and the user is authenticated
     * This is performed only the first time the user logs in with a new provider
     *
     * IMP:Local users (admins) are asked to enter the password when they login using a different provider
     */
    public function verify(Request $request, RulePasswordlessApiLogin $validator)
    {
        //validate request
        $params = $request->all();
        Log::error('Google $params', ['$params' => $params]);
        $validator->validate($params);
        if ($validator->hasErrors()) {
            return redirect()->route('verification-code')->withErrors($validator->errors());
        }

        $code = $params['code'];
        $email = $params['email'];
        $provider = $this->googleProviderLabel;

        $result = $this->validateAppleOrGoogleUserWeb($email, $code, $provider);
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        $user = $result;
        //try to update user details
        try {
            $this->updateGoogleUserDetails($params, $user);
        } catch (Throwable $e) {
            //imp:log in user even if details not updated
            Log::error('Google user object exception', ['exception' => $e->getMessage()]);
        }
        // Login user at this point
        Auth::login($user, false);
        return $this->sendLoginResponse($request);
    }
}
