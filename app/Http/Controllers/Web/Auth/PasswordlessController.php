<?php

namespace ec5\Http\Controllers\Web\Auth;

use ec5\Http\Validation\Auth\RuleRecaptcha;
use ec5\Http\Validation\Auth\RulePasswordlessApiCode;
use ec5\Mail\UserPasswordlessWebMail;
use ec5\Mail\UserPasswordlessApiMail;
use ec5\Http\Validation\Auth\RulePasswordlessWeb;
use ec5\Http\Validation\Auth\RulePasswordlessApiLogin;
use ec5\Models\Eloquent\UserProvider;
use ec5\Models\Users\User;
use Illuminate\Http\Request;
use Config;
use Exception;
use Firebase\JWT\JWT as FirebaseJwt;
use Mail;
use ec5\Models\Eloquent\UserPasswordlessWeb;
use ec5\Models\Eloquent\UserPasswordlessApi;
use Carbon\Carbon;
use DB;
use Log;
use PDOException;
use Webpatser\Uuid\Uuid;
use Auth;
use ec5\Libraries\Utilities\Generators;
use Illuminate\Support\Facades\App;
use ec5\Traits\Auth\ReCaptchaValidation;

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
        $tokenExpiresAt = Config::get('auth.passwordless_token_expire', 300);
        $inputs = $request->all();

        //validate request
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }

        //imp: skip captcha only when testing
        if (!App::environment('testing')) {
            //parse recaptcha response for any errors
            $recaptchaResponse = $inputs['g-recaptcha-response'];
            $recaptchaErrors = $this->getAnyRecaptchaErrors($recaptchaResponse);
            if (!isEmpty($recaptchaErrors)) {
                return redirect()->back()->withErrors($recaptchaErrors);
            }
        }

        $email = $inputs['email'];
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
            $userPasswordless->token = bcrypt($code, ['rounds' => Config::get('auth.bcrypt_rounds')]);
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

    //try to authenticate user
    public function authenticate(Request $request, $token)
    {
        $providerPasswordless = config('ec5Strings.providers.passwordless');
        $isPasswordlessEnabled = in_array($providerPasswordless, $this->authMethods, true);

        //is passwordless auth enabled?
        if (!$isPasswordlessEnabled) {
            // Auth method not allowed
            return redirect()->route('login')->withErrors(['ec5_55']);
        }

        // Extract the key, from the config file.
        $decoded = $this->decodeToken($token);

        if ($decoded === null) {
            return redirect()->route('home')->withErrors(['jwt-passwordless' => ['ec5_373']]);
        }

        $email = $decoded['sub'];

        //get token from db for comparison
        $userPasswordless = UserPasswordlessWeb::where('email', $email)->first();

        //Does the email exists in the token table?
        if ($userPasswordless === null) {
            Log::error('Error validating jwt-passwordless token', ['error' => 'Email does not exist']);
            return redirect()->route('home')->withErrors(['jwt-passwordless' => ['ec5_373']]);
        }

        //check if the token has not expired
        if (!$userPasswordless->isValidToken($decoded)) {
            Log::error('Error validating passwordless token', ['error' => 'Token not valid']);
            return redirect()->route('home')->withErrors(['jwt-passwordless' => ['ec5_373']]);
        }

        //token is valid, remove it
        $userPasswordless->delete();

        //look for the user
        $user = User::where('email', $email)->first();
        if (!$user) {
            //create new user with passwordless provider
            $user = new User();
            $user->name = config('ec5Strings.user_placeholder.passwordless_first_name');
            $user->email = $email;
            $user->server_role = Config::get('ec5Strings.server_roles.basic');
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->save();

            $userProvider = new UserProvider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = Config::get('ec5Strings.providers.passwordless');
            $userProvider->save();
        }

        /**
         * If the user is unverified, set is as verified and add the passwordless provider
         *
         */
        if ($user->state === Config::get('ec5Strings.user_state.unverified')) {
            $user->state = Config::get('ec5Strings.user_state.active');
            //update name if empty
            //happens when users are added to a project before they create an ec5 account
            if ($user->name === '') {
                $user->name = 'User';
            }
            $user->save();

            //add passwordless provider
            $userProvider = new UserProvider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = Config::get('ec5Strings.providers.passwordless');
            $userProvider->save();
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
        if ($user->state === Config::get('ec5Strings.user_state.active')) {

            $userProvider = UserProvider::where('email', $email)->where('provider', $this->passwordlessProviderLabel)->first();

            if (!$userProvider) {
                /**
                 * if the user is active but the passwordless provider is not found,
                 * this user created an account with another provider (Apple or Google or Local)
                 */

                //todo do nothing aside from adding the passwordless provider?
                //add passwordless provider
                $userProvider = new UserProvider();
                $userProvider->email = $email;
                $userProvider->user_id = $user->id;
                $userProvider->provider = Config::get('ec5Strings.providers.passwordless');
                $userProvider->save();
            }
        }

        //Login user
        Auth::login($user, false);
        return $this->sendLoginResponse($request);
    }

    //try to authenticate user
    public function authenticateWithCode(Request $request, RulePasswordlessApiLogin $validator)
    {
        $providerPasswordless = config('ec5Strings.providers.passwordless');
        $isPasswordlessEnabled = in_array($providerPasswordless, $this->authMethods, true);
        //is passwordless auth enabled in production?
        if (!App::environment('testing')) {
            if (!$isPasswordlessEnabled) {
                // Auth method not allowed
                \Log::error('Passwordless auth not enabled');
                return redirect()->route('login')->withErrors($validator->errors());
            }
        }

        //validate request
        $inputs = $request->all();
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            \Log::error('Passwordless auth request error', ['errors' => $validator->errors()]);
            return redirect()->route('login')->withErrors($validator->errors());
        }

        $code = $inputs['code'];
        $email = $inputs['email'];

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
            //create user
            $user = new User();
            $user->name = config('ec5Strings.user_placeholder.passwordless_first_name');
            $user->email = $email;
            $user->server_role = Config::get('ec5Strings.server_roles.basic');
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->save();

            //add passwordless provider
            $userProvider = new UserProvider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = Config::get('ec5Strings.providers.passwordless');
            $userProvider->save();
        }

        /**
         * If the user is unverified, set is as verified and add the passwordless provider
         *
         */
        if ($user->state === Config::get('ec5Strings.user_state.unverified')) {
            $user->state = Config::get('ec5Strings.user_state.active');
            //update name if empty
            //happens when users are added to a project before they create an ec5 account
            if ($user->name === '') {
                $user->name = config('ec5Strings.user_placeholder.passwordless_first_name');
            }
            $user->save();

            //add passwordless provider
            $userProvider = new UserProvider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = Config::get('ec5Strings.providers.passwordless');
            $userProvider->save();
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
        if ($user->state === Config::get('ec5Strings.user_state.active')) {

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
                $userProvider->provider = Config::get('ec5Strings.providers.passwordless');
                $userProvider->save();
            }
        }
        //Login user as passwordless
        Auth::login($user, false);
        return $this->sendLoginResponse($request);
    }

    private function decodeToken($token)
    {
        $jwtConfig = Config::get('auth.jwt-passwordless');
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
