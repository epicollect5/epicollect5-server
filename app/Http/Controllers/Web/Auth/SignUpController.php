<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use Carbon\Carbon;
use DB;
use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Auth\RuleRecaptcha;
use ec5\Http\Validation\Auth\RuleSignup;
use ec5\Mail\UserAccountActivationMail;
use ec5\Models\User\User;
use ec5\Models\User\UserVerify;
use ec5\Traits\Auth\ReCaptchaValidation;
use Exception;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Log;
use Mail;
use PDOException;


class SignUpController extends Controller
{
    use AuthenticatesUsers, ReCaptchaValidation;

    public function __construct()
    {
    }

    public function show()
    {
        return view('auth.signup');
    }

    public function store(Request $request, RuleSignup $validator, RuleRecaptcha $captchaValidator)
    {
        $codeExpiresAt = config('auth.account_code.expire');

        //validate request parameters
        $inputs = $request->all();

        //Validate the form inputs
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }

        //mainly stupid password checks here
        $validator->additionalChecks($inputs);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }

        if (!(App::environment() === 'testing')) {
            //parse recaptcha response for any errors
            $recaptchaResponse = $inputs['g-recaptcha-response'];
            $recaptchaErrors = $this->getAnyRecaptchaErrors($recaptchaResponse);
            if (!empty($recaptchaErrors)) {
                return redirect()->back()->withErrors($recaptchaErrors);
            }
        }

        $user = new User();

        $user->name = trim($inputs['name']);
        $user->email = trim($inputs['email']);
        $user->password = bcrypt($inputs['password'], ['rounds' => config('auth.bcrypt_rounds')]);
        $user->provider = config('epicollect.strings.providers.local');
        $user->server_role = config('epicollect.strings.server_roles.basic');
        $user->state = config('epicollect.strings.user_state.unverified');

        try {
            DB::beginTransaction();
            if ($user->save()) {
                // Check credentials ie email, password and unverified state
                $credentials = array(
                    'email' => $user->email,
                    'password' => $inputs['password'],
                    'state' => config('epicollect.strings.user_state.unverified'),
                    // Only local providers allowed to login this way
                    'provider' => config('epicollect.strings.providers.local')
                );

                // Attempt to log the user in
                if (Auth::attempt($credentials, false)) {

                    $user = Auth::getLastAttempted();

                    if ($user->state !== config('epicollect.strings.user_state.unverified')) {
                        return view('auth.signup')->withErrors(['ec5_12']);
                    }
                    // Log unverified user in
                    Auth::login($user, false);

                    $code = random_int(100000, 999999);

                    //build user verify model and save it for later verification
                    $userVerify = new UserVerify();
                    $userVerify->code = bcrypt($code);
                    $userVerify->user_id = $user->id;
                    $userVerify->email = $user->email;
                    $userVerify->expires_at = Carbon::now()->addSeconds($codeExpiresAt)->toDateTimeString();
                    $userVerify->save();

                    //email verification token
                    Mail::to($user->email)->send(new UserAccountActivationMail(
                        $user->name,
                        $code
                    ));

                    //all good commit
                    DB::commit();

                    // Redirect to verification page
                    return redirect()->route('verify');
                }
            }
        } catch (PDOException $e) {
            Log::error('Error signup local user', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return view('auth.signup')->withErrors(['ec5_376']);
        } catch (\Throwable $e) {
            Log::error('Error signup local user', ['exception' => $e]);
            DB::rollBack();
            return view('auth.signup')->withErrors(['ec5_376']);
        }

        //all good, go to user is not verified yet
        //todo verification pages should be only for logged in users and unverified?
        return view('auth.verification', [
            'name' => $user->name,
            'email' => $user->email
        ]);
    }
}
