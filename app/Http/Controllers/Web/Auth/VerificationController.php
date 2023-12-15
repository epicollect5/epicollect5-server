<?php

namespace ec5\Http\Controllers\Web\Auth;

use ec5\Models\Eloquent\UserVerify;
use Illuminate\Http\Request;
use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Auth\RuleVerification;
use ec5\Models\Eloquent\User;
use Config;
use Auth;
use Log;
use DB;
use PDOException;
use Exception;
use Carbon\Carbon;
use Mail;
use ec5\Mail\UserAccountActivationMail;

class VerificationController extends Controller
{

    public function __construct()
    {
        //show this page only to unverified users (logged in but unverified)
    }


    public function show()
    {
        $user = Auth::User();

        //no user? If means no one is logged in so go to home page
        if ($user === null) {
            return redirect('/');
        }

        return view('auth.verification', [
            'name' => $user->name,
            'email' => $user->email
        ]);
    }

    public function verify(Request $request, RuleVerification $validator)
    {
        $inputs = $request->all();

        $validator->validate($inputs);

        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }

        //check if code is not expired for the logged in user
        $user = Auth::user();
        $code = $inputs['code'];
        $userVerify = UserVerify::where([
            'user_id' => $user->id,
            'email' => $user->email
        ])->first();

        if ($userVerify !== null) {
            if ($userVerify->isValidCode($code)) {
                //set user as verified in the db
                try {
                    DB::beginTransaction();
                    $userVerified = User::find($user->id);
                    $userVerified->state = config('epicollect.strings.user_state.active');
                    $userVerified->save();

                    //remove code row from users_verify table
                    $userVerify->delete();

                    //apply changes
                    DB::commit();
                    //redirect to home page with success toast
                    return redirect('/')->with('message', 'ec5_379');
                } catch (\PDOException $e) {
                    Log::error('Cannot set user as verified', ['exception' => $e->getMessage()]);
                    DB::rollBack();
                } catch (\Exception $e) {
                    Log::error('Cannot set user as verified', ['exception' => $e->getMessage()]);
                    DB::rollBack();
                }
            }
        }
        //by default return error
        return redirect()->back()->withErrors(['errors' => ['ec5_378']]);
    }

    public function resend()
    {
        $codeExpiresAt = config('auth.account_code.expire');

        $user = Auth::user();
        $userVerify = UserVerify::where('user_id', $user->id)
            ->where('email', $user->email)
            ->first();

        //remove current code for this user if found
        if ($userVerify !== null) {
            $userVerify->delete();
        }

        //generate new code for this user
        $code = random_int(100000, 999999);

        //build user verify model and save it for later verification
        $userVerify = new UserVerify();
        $userVerify->code = bcrypt($code);
        $userVerify->user_id = $user->id;
        $userVerify->email = $user->email;
        $userVerify->expires_at = Carbon::now()->addSeconds($codeExpiresAt)->toDateTimeString();

        try {
            DB::beginTransaction();

            if ($userVerify->save()) {
                DB::commit();
            }
        } catch (PDOException $e) {
            Log::error('Error creating new code for user', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return view('auth.verification')->withErrors(['ec5_376']);
        } catch (Exception $e) {
            Log::error('Error creating new code for user', ['exception' => $e]);
            DB::rollBack();
            return view('auth.verification')->withErrors(['ec5_376']);
        }

        //resend code
        try {
            Mail::to($user->email)->send(new UserAccountActivationMail(
                $user->name,
                $code
            ));
        } catch (Exception $e) {
            return view('auth.verification')->withErrors(['ec5_116']);
        }

        //return to view with success message
        return redirect()->route('verify')->with('message', 'ec5_381');
    }
}
