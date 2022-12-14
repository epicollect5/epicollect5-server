<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Validation\Auth\RulePasswordlessApiCode;
use ec5\Http\Validation\Auth\RulePasswordlessApiLogin;
use ec5\Mail\UserPasswordlessApiMail;
use ec5\Models\Users\User;
use Illuminate\Http\Request;
use Config;
use Exception;
use Mail;
use ec5\Models\Eloquent\UserPasswordlessApi;
use Carbon\Carbon;
use DB;
use Log;
use PDOException;
use Auth;
use ec5\Libraries\Utilities\Generators;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Models\Eloquent\UserProvider;
use ec5\Libraries\Jwt\JwtUserProvider;

class PasswordlessController extends AuthController
{
    public function __construct(JwtUserProvider $provider)
    {
        parent::__construct($provider);
    }

    public function sendCode(Request $request, RulePasswordlessApiCode $validator, ApiResponse $apiResponse)
    {
        $tokenExpiresAt = env('PASSWORDLESS_TOKEN_EXPIRES_IN', 300);

        $inputs = $request->all();

        //validate request
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return $apiResponse->errorResponse(400, $validator->errors());
        }

        $email = $inputs['email'];
        $code = Generators::randomNumber(6, 1);

        try {
            DB::beginTransaction();
            //remove any code for this user (if found)
            $userPasswordless = UserPasswordlessApi::where('email', $email);
            if ($userPasswordless !== null) {
                $userPasswordless->delete();
            }

            //add token to db
            $userPasswordless = new UserPasswordlessApi();
            $userPasswordless->email = $email;
            $userPasswordless->code = bcrypt($code, ['rounds' => env('BCRYPT_ROUNDS')]);
            $userPasswordless->expires_at = Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString();
            $userPasswordless->save();

            DB::commit();
        } catch (PDOException $e) {
            Log::error('Error generating passwordless access code via appi');
            DB::rollBack();

            return $apiResponse->errorResponse(400, [
                'passwordless-request-code' => ['ec5_104']
            ]);
        } catch (Exception $e) {
            Log::error('Error generating password access code via api');
            DB::rollBack();

            return $apiResponse->errorResponse(400, [
                'passwordless-request-code' => ['ec5_104']
            ]);
        }

        //send email with verification token
        try {
            Mail::to($email)->send(new UserPasswordlessApiMail($code));
        } catch (Exception $e) {
            return $apiResponse->errorResponse(400, [
                'passwordless-request-code' => ['ec5_116']
            ]);
        }

        //send success response (email sent)
        return $apiResponse->successResponse('ec5_372');
    }

    //try to authenticate user
    public function login(Request $request, ApiResponse $apiResponse,  RulePasswordlessApiLogin $validator)
    {
        //validate request
        $inputs = $request->all();
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            return $apiResponse->errorResponse(400, $validator->errors());
        }

        // Check this auth method is allowed
        if (in_array('passwordless', Config::get('auth.auth_methods'))) {

            $code = $inputs['code'];
            $email = $inputs['email'];

            //get token from db for comparison
            $userPasswordless = UserPasswordlessApi::where('email', $email)->first();

            //Does the email exists?
            if ($userPasswordless === null) {
                Log::error('Error validating passworless code', ['error' => 'Email does not exist']);
                return $apiResponse->errorResponse(400, ['passwordless-api' => ['ec5_378']]);
            }

            //check if the code is valid
            if (!$userPasswordless->isValidCode($code)) {
                Log::error('Error validating passworless code', ['error' => 'Code not valid']);
                return $apiResponse->errorResponse(400, ['passwordless-api' => ['ec5_378']]);
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
            Auth::guard()->login($user, false);
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
            return $apiResponse->toJsonResponse(200, 0);
        }

        // Auth method not allowed
        return $apiResponse->errorResponse(400, [
            'passwordless-api' => ['ec5_55']
        ]);
    }
}
