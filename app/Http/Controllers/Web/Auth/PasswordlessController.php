<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use Carbon\Carbon;
use DB;
use ec5\Http\Validation\Auth\RulePasswordlessApiLogin;
use ec5\Http\Validation\Auth\RulePasswordlessWeb;
use ec5\Http\Validation\Auth\RuleRecaptcha;
use ec5\Libraries\Utilities\Generators;
use ec5\Mail\UserPasswordlessApiMail;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessWeb;
use ec5\Models\User\UserProvider;
use ec5\Services\User\UserService;
use ec5\Traits\Auth\ReCaptchaValidation;
use Exception;
use Firebase\JWT\JWT as FirebaseJwt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Log;
use Mail;
use PDOException;

class PasswordlessController extends AuthController
{
    use ReCaptchaValidation;

    public function __construct()
    {
        parent::__construct();
    }

    public function show()
    {
        return view('auth.verification_passwordless');
    }

    public function sendCode(Request $request, RulePasswordlessWeb $validator, RuleRecaptcha $captchaValidator)
    {
        $tokenExpiresAt = config('auth.passwordless_token_expire', 300);
        $params = $request->all();

        //validate request
        $validator->validate($params);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }

        //imp: skip captcha only when testing
        if (!(App::environment() === 'testing')) {
            //parse recaptcha response for any errors
            if (isset($params['g-recaptcha-response'])) {
                $recaptchaResponse = $params['g-recaptcha-response'];
                $recaptchaErrors = $this->getAnyRecaptchaErrors($recaptchaResponse);
                if (!empty($recaptchaErrors)) {
                    return redirect()->back()->withErrors($recaptchaErrors);
                }
            } else {
                return redirect()->back()->withErrors(['recaptcha' => ['ec5_103']]);
            }
        }

        $email = $params['email'];
        $code = Generators::randomNumber(6, 1);

        try {
            DB::beginTransaction();
            //remove any code for this user (if found)
            $userPasswordless = UserPasswordlessWeb::where('email', $email);
            if ($userPasswordless !== null) {
                $userPasswordless->delete();
            }

            //add token to db
            $userPasswordless = new UserPasswordlessWeb();
            $userPasswordless->email = $email;
            $userPasswordless->token = bcrypt($code, ['rounds' => config('auth.bcrypt_rounds')]);
            $userPasswordless->expires_at = Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString();
            $userPasswordless->save();

            DB::commit();
        } catch (PDOException $e) {
            Log::error('Error generating passwordless access code web');
            DB::rollBack();

            return redirect()->back()->withErrors([
                'exception' => $e->getMessage(),
                'passwordless-request-code' => ['ec5_104']
            ]);
        } catch (Exception $e) {
            Log::error('Error generating passwordless access code web');
            DB::rollBack();

            return redirect()->back()->withErrors([
                'exception' => $e->getMessage(),
                'passwordless-request-code' => ['ec5_104']
            ]);
        }

        //send email with verification code
        try {
            Mail::to($email)->send(new UserPasswordlessApiMail($code));
        } catch (Exception $e) {
            return redirect()->back()->withErrors([
                'exception' => $e->getMessage(),
                'passwordless-request-code' => ['ec5_116']
            ]);
        }

        //send success response (email sent) and show validation screen
        return view(
            'auth.verification_passwordless',
            [
                'email' => $email
            ]
        );
    }

    //try to authenticate user by checking provided numeric OTP
    public function authenticateWithCode(Request $request, RulePasswordlessApiLogin $validator)
    {
        $providerPasswordless = config('epicollect.strings.providers.passwordless');
        $isPasswordlessEnabled = in_array($providerPasswordless, $this->authMethods, true);
        //is passwordless auth enabled in production?
        if (!(App::environment() === 'testing')) {
            if (!$isPasswordlessEnabled) {
                // Auth method is not allowed
                \Log::error('Passwordless auth not enabled');
                return redirect()->route('login')->withErrors($validator->errors());
            }
        }

        //validate request
        $params = $request->all();
        $validator->validate($params);
        if ($validator->hasErrors()) {
            \Log::error('Passwordless auth request error', ['errors' => $validator->errors()]);
            return redirect()->route('login')->withErrors($validator->errors());
        }

        $code = $params['code'];
        $email = $params['email'];

        //get token from db for comparison (passwordless web table)
        //imp: we use the web table for legacy reasons and also to avoid
        //imp: issues when users are logging in to both the web and the mobile app
        //imp: at the same time. Only one token can exist at any given time
        //imp: so the last request would remove the token from the first one
        $userPasswordless = UserPasswordlessWeb::where('email', $email)->first();

        //Does the email exists?
        if ($userPasswordless === null) {
            Log::error('Error validating passworless code', [
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
            Log::error('Invalid passworless code', [
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
         * if user exists and it is active, just log the user in.
         * This means the user owns the email used, if the user owns a Google and Apple account
         * matching the email, that will give the user access to those projects.
         * Apple and Google verify the email on their side so we are safe
         *
         * Same goes for local users
         */

        /**
         * User was found and active, does this user have a passwordless provider?
         */
        if ($user->state === config('epicollect.strings.user_state.active')) {

            $userProvider = UserProvider::where('email', $email)->where('provider', $this->passwordlessProviderLabel)->first();

            if (!$userProvider) {
                /**
                 * if the user is active but the passwordless provider is not found,
                 * this user created an account with another provider (Apple or Google or Local)
                 */

                //todo: do nothing aside from adding the passwordless provider?
                //add passwordless provider
                $userProvider = new UserProvider();
                $userProvider->email = $email;
                $userProvider->user_id = $user->id;
                $userProvider->provider = config('epicollect.strings.providers.passwordless');
                $userProvider->save();
            }
        }
        //Login user as passwordless
        Auth::login($user, false);
        return $this->sendLoginResponse($request);
    }

    private function decodeToken($token)
    {
        $jwtConfig = config('auth.jwt-passwordless');
        $secretKey = $jwtConfig['secret_key'];
        $decoded = null;

        try {
            $decoded = (array)FirebaseJwt::decode($token, $secretKey, array('HS256'));
        } catch (Exception $e) {
            Log::error('Error decoding jwt-passwordless token to login', ['exception' => $e->getMessage()]);
        }
        return $decoded;
    }
}
