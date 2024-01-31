<?php

namespace ec5\Http\Controllers\Web\Auth;

use Carbon\Carbon;
use DB;
use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Auth\RuleForgot;
use ec5\Http\Validation\Auth\RuleRecaptcha;
use ec5\Mail\UserPasswordResetMail;
use ec5\Models\User\User;
use ec5\Models\User\UserResetPassword;
use ec5\Traits\Auth\ReCaptchaValidation;
use Exception;
use Firebase\JWT\JWT as FirebaseJwt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Log;
use Mail;
use PDOException;
use Ramsey\Uuid\Uuid;

class ForgotPasswordController extends Controller
{
    /*
   |--------------------------------------------------------------------------
   | Password Reset Controller
   |--------------------------------------------------------------------------
   |
   | This controller is responsible for handling password reset emails
   |
   */

    use ReCaptchaValidation;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function show()
    {
        return view('auth.passwords.forgot');
    }

    public function sendResetEmail(Request $request, RuleForgot $validator, RuleRecaptcha $captchaValidator)
    {
        $tokenExpiresAt = config('auth.jwt-forgot.expire');

        //validate request
        $inputs = $request->all();

        //Validate the form inputs
        $validator->validate($inputs);
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

        //try to find local user
        //Google user cannot reset the password as they do not have one
        //If anyone has a local account first and later adds a Google Account with the
        //same email, they are still local users who can login with both methods
        $user = User::where('email', $inputs['email'])
            ->where('provider', config('epicollect.strings.providers.local'))
            ->first();

        //send actual email only if the user exists
        if ($user !== null) {
            //generate token jwt
            $jwtConfig = config('auth.jwt-forgot');
            try {
                // Extract the key, from the config file.
                $secretKey = $jwtConfig['secret_key'];
                $expiryTime = time() + $jwtConfig['expire'];

                $data = array(
                    'iss' => config('app.url'), // issuer
                    'iat' => time(), // issued at time
                    'jti' => Uuid::uuid4()->toString(), // unique token uuid v4
                    'exp' => $expiryTime, // expiry time
                    'sub' => $user->id, // subject i.e. user id
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
                    'forgot-password' => ['ec5_104']
                ]);
            }

            try {
                DB::beginTransaction();
                //remove any token for this user (if found)
                $userResetPassword = UserResetPassword::where('user_id', $user->id);
                if ($userResetPassword !== null) {
                    $userResetPassword->delete();
                }

                //add token to db
                $userResetPassword = new UserResetPassword();
                $userResetPassword->user_id = $user->id;
                $userResetPassword->token = $token;
                $userResetPassword->expires_at = Carbon::now()->addSeconds($tokenExpiresAt)->toDateTimeString();
                $userResetPassword->save();

                DB::commit();
            } catch (PDOException $e) {
                Log::error('Error generating password reset token');
                DB::rollBack();
                return redirect()->back()->withErrors(['forgot-password' => ['ec5_104']]);
            } catch (Exception $e) {
                Log::error('Error generating password reset token');
                DB::rollBack();
                return redirect()->back()->withErrors(['forgot-password' => ['ec5_104']]);
            }
            try {
                //send email with verification token (only if user exist)
                Mail::to($user->email)->send(new UserPasswordResetMail(
                    $user->name,
                    $token
                ));
            } catch (Exception $e) {
                return redirect()->back()->withErrors(['forgot-password' => ['ec5_116']]);
            }
        }

        //back with success message (anyway) so we do not expose emails actually being on our system
        return redirect()->route('forgot-show')->with('message', 'ec5_365');
    }
}
