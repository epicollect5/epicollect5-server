<?php

namespace ec5\Http\Controllers\Web\Auth;

use ec5\Http\Validation\Auth\RuleRecaptcha;
use ec5\Mail\UserPasswordlessWebMail;
use ec5\Http\Validation\Auth\RulePasswordlessWeb;
use ec5\Models\Eloquent\UserProvider;
use ec5\Models\Users\User;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Config;
use Exception;
use Firebase\JWT\JWT as FirebaseJwt;
use Mail;
use ec5\Models\Eloquent\UserPasswordlessWeb;
use Carbon\Carbon;
use DB;
use Log;
use PDOException;
use Webpatser\Uuid\Uuid;
use Auth;

class PasswordlessController extends AuthController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function sendLink(Request $request, RulePasswordlessWeb $validator, RuleRecaptcha $captchaValidator)
    {

        $tokenExpiresAt = env('PASSWORDLESS_TOKEN_EXPIRES_IN', 300);

        $inputs = $request->all();

        //validate request
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }

        //get recaptcha response
        $client = new Client(); //GuzzleHttp\Client
        $response = $client->post(env('GOOGLE_RECAPTCHA_API_VERIFY_ENDPOINT'), [
            'form_params' => [
                'secret' => env('GOOGLE_RECAPTCHA_SECRET_KEY'),
                'response' => $inputs['g-recaptcha-response']
            ]
        ]);

        /**
         * Validate the captcha response first
         */
        $arrayResponse = json_decode($response->getBody()->getContents(), true);

        $captchaValidator->validate($arrayResponse);
        if ($captchaValidator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($captchaValidator->errors());
        }

        $captchaValidator->additionalChecks($arrayResponse);
        if ($captchaValidator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($captchaValidator->errors());
        }

        $email = $inputs['email'];

        //generate token jwt
        $jwtConfig = Config::get('auth.jwt-passwordless');
        try {
            // Extract the key, from the config file.
            $secretKey = $jwtConfig['secret_key'];
            $expiryTime = time() + env('PASSWORDLESS_TOKEN_EXPIRES_IN', 300);

            $data = array(
                'iss' => Config::get('app.url'), // issuer
                'iat' => time(), // issued at time
                'jti' => (string)Uuid::generate(4), // unique token uuid v4
                'exp' => $expiryTime, // expiry time
                'sub' => $email, //  user email
            );

            /**
             *
             *
             * iss:The issuer of the token
             * sub: The subject of the token
             * aud: The audience of the token
             * exp: Token expiration time defined in Unix time
             * nbf: “Not before” time that identifies the time before which the token must not be accepted for processing
             * iat: “Issued at” time, in Unix time, at which the token was issued
             * jti: JWT ID claim provides a unique identifier for the web token // Encode the array to a JWT string.
             */

            $token = FirebaseJwt::encode(
                $data,      // Data to be encoded in the JWT
                $secretKey, // The signing key
                'HS256' // The signing algorithm
            );
        } catch (Exception $e) {
            return redirect()->back()->withErrors([
                'exception' => $e->getMessage(),
                'passwordless-request-token' => ['ec5_104']
            ]);
        }

        try {
            DB::beginTransaction();
            //remove any token for this user (if found)
            $userPasswordless = UserPasswordlessWeb::where('email', $email);
            if ($userPasswordless !== null) {
                $userPasswordless->delete();
            }

            //add token to db
            $userPasswordless = new UserPasswordlessWeb();
            $userPasswordless->email = $email;
            $userPasswordless->token = $token;
            $userPasswordless->expires_at = Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString();
            $userPasswordless->save();

            DB::commit();
        } catch (PDOException $e) {
            Log::error('Error generating passwordless access token');
            DB::rollBack();
            return redirect()->back()->withErrors(['passwordless-request' => ['ec5_104']]);
        } catch (Exception $e) {
            Log::error('Error generating password access token');
            DB::rollBack();
            return redirect()->back()->withErrors(['passwordless-request' => ['ec5_104']]);
        }

        //send email with verification token
        try {
            Mail::to($email)->send(new UserPasswordlessWebMail(
                $token,
                $email
            ));
        } catch (Exception $e) {
            Log::error('Error sending email', ['exception' => $e->getMessage()]);
            return redirect()->back()->withErrors(['passwordless-request' => ['ec5_116']]);
        }

        return redirect()->route('login')->with('message', 'ec5_372');
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
            Log::error('Error validating jwt-forgot token', ['error' => 'Token not valid']);
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
