<?php

namespace ec5\Http\Controllers\Web\Auth;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use ec5\Models\Eloquent\UserVerify;
use Illuminate\Http\Request;
use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Auth\RuleSignup;
use ec5\Http\Validation\Auth\RuleRecaptcha;
use ec5\Models\Users\User;
use Config;
use PDOException;
use Exception;
use Log;
use Mail;
use ec5\Mail\UserAccountActivationMail;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Auth;
use Carbon\Carbon;
use DB;

class SignUpController extends Controller
{

    use AuthenticatesUsers;

    public function __construct()
    {
    }

    public function show()
    {
        return view('auth.signup');
    }

    public function store(Request $request, RuleSignup $validator, RuleRecaptcha $captchaValidator)
    {
        $codeExpiresAt = Config::get('auth.account_code.expire');

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

        $client = new Client(); //GuzzleHttp\Client
        $response = $client->post(Config::get('ec5Setup.google_recaptcha.verify_endpoint'), [
            'form_params' => [
                'secret' => Config::get('ec5Setup.google_recaptcha.secret_key'),
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

        $user = new User();

        $user->name = trim($inputs['name']);
        $user->email = trim($inputs['email']);
        $user->password = bcrypt($inputs['password'], ['rounds' => Config::get('auth.bcrypt_rounds')]);
        $user->provider = Config::get('ec5Strings.providers.local');
        $user->server_role = Config::get('ec5Strings.server_roles.basic');
        $user->state = Config::get('ec5Strings.user_state.unverified');

        try {
            DB::beginTransaction();
            if ($user->save()) {
                // Check credentials ie email, password and unverified state
                $credentials = array(
                    'email' => $user->email,
                    'password' => $inputs['password'],
                    'state' => Config::get('ec5Strings.user_state.unverified'),
                    // Only local providers allowed to login this way
                    'provider' => Config::get('ec5Strings.providers.local')
                );

                // Attempt to log the user in
                if (Auth::attempt($credentials, false)) {

                    $user = Auth::getLastAttempted();

                    if ($user->state !== Config::get('ec5Strings.user_state.unverified')) {
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
        } catch (Exception $e) {
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
