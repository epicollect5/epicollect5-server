<?php

namespace ec5\Http\Controllers\Web\Auth;

use Carbon\Carbon;
use DB;
use ec5\Libraries\Utilities\Generators;
use ec5\Mail\UserPasswordlessApiMail;
use ec5\Models\User\UserPasswordlessApi;
use Exception;
use Log;
use Mail;
use PDOException;

class VerificationCodeController extends AuthController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function show()
    {
        //getting here with email in session, send verification code via email
        if (session()->has('email')) {
            $email = session('email');
            $provider = session('provider');
            $name = session('name');
            $lastName = session('last_name');


            //getting here with errors, just show errors
            if (session()->has('errors')) {
                Log::error('Error verifying account ', ['errors' => session('errors')]);
                return view('auth.verification_code', [
                    'email' => $email,
                    'provider' => $provider,
                    'name' => $name,
                    'last_name' => $lastName
                ]);
            }

            //send verification code
            $tokenExpiresAt = config('auth.passwordless_token_expire', 300);
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
                Log::error('Error generating passwordless access code via appi');
                DB::rollBack();
                return redirect()->route('login')->withErrors(['ec5_104']);
            } catch (Exception $e) {
                Log::error('Error generating password access code via api');
                DB::rollBack();
                return redirect()->route('login')->withErrors(['ec5_104']);
            }

            //send email with verification token
            try {
                Mail::to($email)->send(new UserPasswordlessApiMail($code));
            } catch (Exception $e) {
                return redirect()->route('login')->withErrors(['ec5_116']);
            }

            return view('auth.verification_code', [
                'email' => $email,
                'provider' => $provider,
                'name' => $name,
                'last_name' => $lastName
            ]);
        }

        //return to login page with general error
        return redirect()->route('login')->withErrors(['ec5_103']);
    }
}
