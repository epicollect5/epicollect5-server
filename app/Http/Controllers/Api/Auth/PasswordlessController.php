<?php

/** @noinspection DuplicatedCode */

namespace ec5\Http\Controllers\Api\Auth;

use Auth;
use Carbon\Carbon;
use DB;
use ec5\Http\Validation\Auth\RulePasswordlessApiCode;
use ec5\Http\Validation\Auth\RulePasswordlessApiLogin;
use ec5\Libraries\Auth\Jwt\JwtUserProvider;
use ec5\Libraries\Utilities\Generators;
use ec5\Mail\UserPasswordlessApiMail;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessApi;
use ec5\Models\User\UserProvider;
use ec5\Services\User\UserService;
use ec5\Traits\Auth\PasswordlessProviderHandler;
use Log;
use Mail;
use PDOException;
use Response;
use Throwable;

class PasswordlessController extends AuthController
{
    use PasswordlessProviderHandler;

    public function __construct(JwtUserProvider $provider)
    {
        parent::__construct($provider);
    }

    /**
     * @throws Throwable
     */
    public function sendCode(RulePasswordlessApiCode $validator)
    {
        $tokenExpiresAt = config('auth.passwordless_token_expire', 300);
        $inputs = request()->all();

        //validate request
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return Response::apiErrorCode(400, $validator->errors());
        }

        $email = $inputs['email'];
        //check if email is whitelisted
        if (!UserService::isAuthenticationDomainAllowed($email)) {
            Log::error('Email not whitelisted', ['email' => $email]);
            return Response::apiErrorCode(400, ['passwordless-request-code' => ['ec5_266']]);
        }

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
            $userPasswordless->code = bcrypt($code, ['rounds' => config('auth.bcrypt_rounds')]);
            $userPasswordless->expires_at = Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString();
            $userPasswordless->save();

            DB::commit();
        } catch (PDOException $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return Response::apiErrorCode(400, [
                'passwordless-request-code' => ['ec5_104']
            ]);
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return Response::apiErrorCode(400, [
                'passwordless-request-code' => ['ec5_104']
            ]);
        }

        //send email with verification token
        try {
            Mail::to($email)->send(new UserPasswordlessApiMail($code));
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return Response::apiErrorCode(400, [
                'passwordless-request-code' => ['ec5_116']
            ]);
        }

        //send success response (email sent)
        return Response::apiSuccessCode('ec5_372');
    }
    /**
     * @throws Throwable
     */
    public function login(RulePasswordlessApiLogin $validator)
    {
        //validate request
        $inputs = request()->all();
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            return Response::apiErrorCode(400, $validator->errors());
        }

        // Check this auth method is allowed
        if (in_array('passwordless', config('auth.auth_methods'))) {

            $code = $inputs['code'];
            $email = $inputs['email'];

            //check if email is whitelisted
            if (!UserService::isAuthenticationDomainAllowed($email)) {
                Log::error('Email not whitelisted', ['email' => $email]);
                return Response::apiErrorCode(400, ['passwordless-api' => ['ec5_266']]);
            }

            //get token from db for comparison
            $userPasswordless = UserPasswordlessApi::where('email', $email)->first();

            //Does the email exists?
            if ($userPasswordless === null) {
                Log::error('Error validating passwordless code', ['error' => 'Email does not exist']);
                return Response::apiErrorCode(400, ['passwordless-api' => ['ec5_378']]);
            }

            //check if the code is valid
            if (!$userPasswordless->isValidCode($code)) {
                Log::error('Error validating passwordless code', ['error' => 'Code not valid']);
                return Response::apiErrorCode(400, ['passwordless-api' => ['ec5_378']]);
            }

            //code is valid, remove it
            $userPasswordless->delete();

            //look for existing user
            $user = User::where('email', $email)->first();
            if ($user === null) {
                //create the new user as passwordless
                $user = UserService::createPasswordlessUser($email);
                if (!$user) {
                    return Response::apiErrorCode(400, ['passwordless-api' => ['ec5_376']]);
                }
            }

            /**
             * If the user is unverified, set is as verified and add the passwordless provider
             *
             */
            if ($user->state === config('epicollect.strings.user_state.unverified')) {
                $user->state = config('epicollect.strings.user_state.active');
                //update name if empty
                //happens when users are added to a project before they create an ec5 account
                if ($user->name === '') {
                    $user->name = config('epicollect.mappings.user_placeholder.passwordless_first_name');
                }
                $user->save();

                //add passwordless provider
                $userProvider = new UserProvider();
                $userProvider->email = $user->email;
                $userProvider->user_id = $user->id;
                $userProvider->provider = config('epicollect.strings.providers.passwordless');
                $userProvider->save();
            }

            /**
             * if user exists, and it is active, just log the user in.
             * This means the user owns the email used, if the user owns a Google and Apple account
             * matching the email, that will give the user access to those projects.
             * Apple and Google verify the email on their side so we are safe
             *
             * Same goes for local users
             */

            //User was found, does this user need a passwordless provider?
            $this->addPasswordlessProviderIfMissing($user, $email);

            //Login user as passwordless
            Auth::guard()->login($user, false);
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
            return Response::apiData($data, $meta);
        }

        // Auth method not allowed
        return Response::apiErrorCode(400, [
            'passwordless-api' => ['ec5_55']
        ]);
    }
}
