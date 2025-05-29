<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use ec5\Http\Validation\Auth\RulePasswordlessApiLogin;
use ec5\Http\Validation\Auth\RulePasswordlessWeb;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessWeb;
use ec5\Services\User\UserService;
use ec5\Traits\Auth\PasswordlessProviderHandler;
use ec5\Traits\Auth\ReCaptchaValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Log;
use Throwable;

class PasswordlessController extends AuthController
{
    use ReCaptchaValidation;
    use PasswordlessProviderHandler;

    public function __construct()
    {
        parent::__construct();
    }

    public function show()
    {
        return view('auth.verification_passwordless');
    }

    /**
     * @throws Throwable
     */
    public function sendCode(Request $request, RulePasswordlessWeb $rulePasswordlessWeb)
    {
        $params = $request->all();

        //validate request
        $rulePasswordlessWeb->validate($params);
        if ($rulePasswordlessWeb->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($rulePasswordlessWeb->errors());
        }

        //imp: skip captcha validation only when testing OR when disabled in the .env
        $isGoogleRecaptchaEnabled = config('epicollect.setup.google_recaptcha.use_google_recaptcha');
        if (!(App::environment() === 'testing')  && $isGoogleRecaptchaEnabled) {
            //parse recaptcha response for any errors
            if (isset($params['g-recaptcha-response'])) {
                $recaptchaResponse = $params['g-recaptcha-response'];
                try {
                    $recaptchaErrors = $this->getAnyRecaptchaErrors($recaptchaResponse);
                    if (!empty($recaptchaErrors)) {
                        return redirect()->back()->withErrors($recaptchaErrors);
                    }
                } catch (Throwable $e) {
                    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
                    return redirect()->back()->withErrors(['recaptcha' => ['ec5_103']]);

                }
            } else {
                return redirect()->back()->withErrors(['recaptcha' => ['ec5_103']]);
            }
        }

        $email = $params['email'];
        //check if email is whitelisted
        if (!UserService::isAuthenticationDomainAllowed($email)) {
            Log::error('Email not whitelisted', ['email' => $email]);
            return redirect()->back()->withErrors(['ec5_266']);
        }

        return $this->dispatchAuthToken($email);
    }

    //try to authenticate user by checking provided numeric OTP

    /**
     * @throws Throwable
     */
    public function authenticateWithCode(Request $request, RulePasswordlessApiLogin $validator)
    {
        $providerPasswordless = config('epicollect.strings.providers.passwordless');
        $isPasswordlessEnabled = in_array($providerPasswordless, $this->authMethods, true);
        //is passwordless auth enabled in production?
        if (!(App::environment() === 'testing')) {
            if (!$isPasswordlessEnabled) {
                // Auth method is not allowed
                Log::error('Passwordless auth not enabled');
                return redirect()->route('login')->withErrors($validator->errors());
            }
        }

        //validate request
        $params = $request->all();
        $validator->validate($params);
        if ($validator->hasErrors()) {
            Log::error('Passwordless auth request error', ['errors' => $validator->errors()]);
            return redirect()->route('login')->withErrors($validator->errors());
        }

        $code = $params['code'];
        $email = $params['email'];

        //check if email is whitelisted
        if (!UserService::isAuthenticationDomainAllowed($email)) {
            Log::error('Email not whitelisted', ['email' => $email]);
            return redirect()->route('login')->withErrors(['ec5_266']);
        }

        //get token from db for comparison (passwordless web table)
        //imp: we use the web table for legacy reasons and also to avoid
        //imp: issues when users are logging in to both the web and the mobile app
        //imp: at the same time. Only one token can exist at any given time
        //imp: so the last request would remove the token from the first one
        $userPasswordless = UserPasswordlessWeb::where('email', $email)->first();

        //Does the email exists?
        if ($userPasswordless === null) {
            Log::error('Error validating passwordless code', [
                'error' => 'Email does not exist',
                'email' => $email
            ]);
            // Redirect back if errors
            return view('auth.verification_passwordless', [
                'email' => $email
            ])->withErrors(['ec5_378']);
        }

        //check if the code is valid
        if (!$userPasswordless->isValidCode($code)) {
            Log::error('Invalid passwordless code', [
                'error' => 'Code not valid',
                'email' => $email,
                'code' => $code
            ]);
            //after too many attempts, redirect to login page
            if ($userPasswordless->attempts <= 0) {
                $userPasswordless->delete();
                return redirect()->route('login')->withErrors(['ec5_378']);
            }
            //invalid code but still some attempts left, show same view
            return view('auth.verification_passwordless', [
                'email' => $email
            ])->withErrors(['ec5_378']);
        }

        //code is valid, remove it
        $userPasswordless->delete();

        //look for existing user
        $user = User::where('email', $email)->first();
        if ($user === null) {
            //create the new user as passwordless
            $user = UserService::createPasswordlessUser($email);
            if (!$user) {
                //database error, user might retry
                return view('auth.verification_passwordless', [
                    'email' => $email
                ])->withErrors(['ec5_104']);
            }
        }

        /**
         * If the user is unverified, set is as verified and add the passwordless provider
         *
         */
        if ($user->state === config('epicollect.strings.user_state.unverified')) {
            if (!UserService::updateUnverifiedPasswordlessUser($user)) {
                //database error, user might retry
                return view('auth.verification_passwordless', [
                    'email' => $email
                ])->withErrors(['ec5_104']);
            }
        }

        /**
         * if user exists, and it is active, just log the user in.
         * This means the user owns the email used, if the user owns a Google and Apple account
         * matching the email, that will give the user access to those projects.
         * Apple and Google verify the email on their side so we are safe
         *
         * Same goes for local users
         */

        //User is found at this point, does this user need a passwordless provider?
        $this->addPasswordlessProviderIfMissing($user, $email);
        //Login user as passwordless
        Auth::login($user, false);
        return $this->sendLoginResponse($request);
    }
}
