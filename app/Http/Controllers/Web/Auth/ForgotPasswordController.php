<?php

namespace ec5\Http\Controllers\Web\Auth;

use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Auth\RuleForgot;
use ec5\Http\Validation\Auth\RuleRecaptcha;
use ec5\Mail\UserPasswordResetMail;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use ec5\Models\Eloquent\User;
use Config;
use Exception;
use Firebase\JWT\JWT as FirebaseJwt;
use Mail;
use ec5\Models\Eloquent\UserResetPassword;
use Carbon\Carbon;
use DB;
use Log;
use PDOException;
use Webpatser\Uuid\Uuid;

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
        $tokenExpiresAt = env('JWT_FORGOT_EXPIRE', 3600);

        //validate request
        $inputs = $request->all();

        //Validate the form inputs
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

        //try to find local user
        //Google user cannot reset the password as they do not have one
        //If anyone has a local account first and later adds a Google Account with the
        //same email, they are still local users who can login with both methods
        $user = User::where('email', $inputs['email'])
            ->where('provider', Config::get('ec5Strings.providers.local'))
            ->first();

        //send actual email only if the user exists
        if ($user !== null) {
            //generate token jwt
            $jwtConfig = Config::get('auth.jwt-forgot');
            try {
                // Extract the key, from the config file.
                $secretKey = $jwtConfig['secret_key'];
                $expiryTime = time() + $jwtConfig['expire'];

                $data = array(
                    'iss' => Config::get('app.url'), // issuer
                    'iat' => time(), // issued at time
                    'jti' => (string)Uuid::generate(4), // unique token uuid v4
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
                 * nbf: ???Not before??? time that identifies the time before which the token must not be accepted for processing
                 * iat: ???Issued at??? time, in Unix time, at which the token was issued
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
