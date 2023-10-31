<?php

namespace ec5\Http\Controllers\Web\Auth;

use ec5\Http\Controllers\Controller;
use ec5\Models\Eloquent\UserProvider;
use ec5\Models\Eloquent\UserResetPassword;
use ec5\Models\Eloquent\User;
use ec5\Http\Validation\Auth\RuleReset;
use Illuminate\Http\Request;
use Firebase\JWT\JWT as FirebaseJWT;
use PDOException;
use Exception;
use Config;
use Log;
use DB;
use Auth;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    |
    */

    public function __construct()
    {

    }

//    public function show($token)
//    {
//        // Extract the key, from the config file.
//        $decoded = $this->decodeToken($token);
//
//        if($decoded === null) {
//            return redirect()->route('home')->withErrors(['jwt-forgot' => ['ec5_74']]);
//        }
//
//        $userId = $decoded['sub'];
//
//        //get token from db for comparison
//        $userResetPassword = UserResetPassword::where('user_id', $userId)->first();
//
//        //Does the user exists?
//        if($userResetPassword === null) {
//            Log::error('Error validating jwt-forgot token', ['error' => 'User does not exists']);
//            return redirect()->route('home')->withErrors(['jwt-forgot' => ['ec5_74']]);
//        }
//
//        //check if the token has not expired
//        if(!$userResetPassword->isValidToken($decoded)) {
//            Log::error('Error validating jwt-forgot token', ['error' => 'Token not valid']);
//            return redirect()->route('home')->withErrors(['jwt-forgot' => ['ec5_74']]);
//        }
//
//        //if token is valid, pass it to the view, else bail out
//        return view('auth.passwords.reset', ['token' => $token]);
//    }

    public function show()
    {
        $user = Auth::user();
        //reset user password view (only if the user is a local one!)
        $userProvider = UserProvider::where('user_id', $user->id)
            ->where('provider', config('ec5Strings.providers.local'))->first();

        if ($userProvider === null) {
            Log::error('Error user not a local one', ['error' => 'Not a local user']);
            return redirect()->route('home');
        }

        return view('staff.reset');
    }

    public function reset(Request $request, RuleReset $validator)
    {
        //validate request
        $user = Auth::user();
        $inputs = $request->all();

        //Validate the form inputs
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }

        //mainly stupid password checks here
        $validator->additionalChecks($inputs, $user->email);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }


        //reset user password (only if the user is a local one!)
        $userProvider = UserProvider::where('user_id', $user->id)
            ->where('provider', config('ec5Strings.providers.local'))->first();

        if ($userProvider === null) {
            Log::error('Error user not a local one', ['error' => 'Not a local user']);
            return redirect()->route('home')->withErrors(['staff-reset' => ['ec5_366']]);
        }

        $user->password = bcrypt($inputs['password'], ['rounds' => Config::get('auth.bcrypt_rounds')]);
        try {
            DB::beginTransaction();
            $user->save();
            DB::commit();
        } //the token is sent back to the user to retry submitting the form when db error occurs
        catch (PDOException $e) {
            Log::error('Error password reset', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return redirect()->back()->withErrors(['ec5_104']);
        } catch (Exception $e) {
            Log::error('Error password reset', ['exception' => $e]);
            DB::rollBack();
            return redirect()->back()->withErrors(['ec5_104']);
        }
        //return to profile page with success message
        return redirect()->route('profile')->with('message', 'ec5_73');
    }
}
