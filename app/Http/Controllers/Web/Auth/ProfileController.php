<?php

namespace ec5\Http\Controllers\Web\Auth;

use ec5\Http\Controllers\Controller;
use ec5\Models\Users\User;
use ec5\Models\Eloquent\UserProvider;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\InvalidStateException;
use Exception;
use ec5\Libraries\JwtApple\JWT as JWTApple;
use ec5\Libraries\JwtApple\JWK as JWKApple;

use Laravel\Socialite\Facades\Socialite;
use Config;
use View;
use Auth;
use Ldap;
use Log;

class ProfileController extends Controller
{
    protected $providers;
    protected $user;
    protected $appleProviderLabel;
    protected $googleProviderLabel;

    public function __construct()
    {
        //get Auth in constructor using a closure, needed for 5.3+ -> t.ly/vuxc
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            $this->providers = UserProvider::where('email', $this->user->email)
                ->pluck('provider')->toArray();

            $this->appleProviderLabel = Config::get('ec5Strings.providers.apple');
            $this->googleProviderLabel = Config::get('ec5Strings.providers.google');

            return $next($request);
        });
    }

    /**
     * Show the application login form.
     *
     * @return View
     */
    public function show(Request $request)
    {
        $request->session()->put('nonce', csrf_token());

        return view('auth.profile', [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'providers' => $this->providers,
            'auth_methods' => config('auth.auth_methods')
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * Log out users so they can reset their password
     */
    public function reset(Request $request)
    {
        //logout
        Auth::logout();
        $request->session()->flush();
        $request->session()->regenerate();

        return redirect()->route('forgot-show');
    }

    public function connectGoogle(Request $request)
    {

        //local users cannot connect Google
        if ($this->isLocal($this->user)) {
            return redirect()->back();
        }

        return Socialite::with('google')
            ->with(['prompt' => 'select_account']) //todo not sure we might remove this
            ->redirectUrl(env('GOOGLE_CONNECT_REDIRECT_URI'))
            ->redirect();
    }

    public function disconnectGoogle()
    {
        UserProvider::where('email', $this->user->email)
            ->where('provider', $this->googleProviderLabel)->delete();

        return redirect()->route('profile')->with([
            'message' => 'ec5_385',
        ]);
    }

    public function disconnectApple()
    {
        UserProvider::where('email', $this->user->email)
            ->where('provider', $this->appleProviderLabel)->delete();

        return redirect()->route('profile')->with([
            'message' => 'ec5_385',
        ]);
    }

    /**
     * @param Request $request
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function handleGoogleConnectCallback(Request $request)
    {
        try {
            // Find the google user
            $googleUser = Socialite::with('google')
                ->redirectUrl(env('GOOGLE_CONNECT_REDIRECT_URI'))
                ->user();

            // If we found a google user
            if ($googleUser) {
                //check email is the same to the logged in user
                if ($googleUser->email === $this->user->email) {
                    //add google provider, to allow loggin in with both methods
                    $googleProvider = new UserProvider();
                    $googleProvider->email = $this->user->email;
                    $googleProvider->user_id = $this->user->id;
                    $googleProvider->provider = Config::get('ec5Strings.providers.google');

                    //if name is either Apple User or User, update user
                    if ($this->user->name === config('ec5Strings.user_placeholder.apple_first_name')) {
                        $this->updateUserDetailsWithGoogle($googleUser);
                    }
                    if ($this->user->name === config('ec5Strings.user_placeholder.passwordless_first_name')) {
                        $this->updateUserDetailsWithGoogle($googleUser);
                    }

                    $googleProvider->save();

                    //redirect with success message
                    return redirect()->route('profile')
                        ->with([
                            'message' => 'ec5_379',
                        ]);
                }
                //users do not match, error
                return redirect()->route('profile')->withErrors(['ec5_370']);
            }
            //google user not found, redirect with error
            return redirect()->route('profile')->withErrors(['ec5_369']);
        } catch (InvalidStateException $e) {
            Log::error('Google Login Web Exception: ', ['exception' => [$e]]);
            return redirect()->route('profile')->withErrors(['ec5_369']);
        } catch (Exception $e) {
            Log::error('Google Connect Web Exception: ', [
                'exception' => $e->getMessage()
            ]);
            return redirect()->route('profile')->withErrors(['ec5_369']);
        }
    }

    //this is going to be a post
    public function handleAppleConnectCallback(Request $request)
    {
        $appleUser = null;

        //local users cannot connect Apple
        if ($this->isLocal($this->user)) {
            return view('auth.profile', [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'providers' => $this->providers,
                'auth_methods' => config('auth.auth_methods')
            ]);
        }

        try {
            $nonce = session('nonce');
            $params = $request->all();
            $token = $params['id_token'];

            // Log::error('Apple request', ['$params' => $params]);

            //get public keys from Apple endpoint
            $apple_jwk_keys = json_decode(file_get_contents(env('APPLE_PUBLIC_KEYS_ENDPOINT')), null, 512, JSON_OBJECT_AS_ARRAY);
            $keys = array();
            foreach ($apple_jwk_keys->keys as $key) {
                $keys[] = (array)$key;
            }
            $jwks = ['keys' => $keys];

            //get kid from jwy header
            $header_base_64 = explode('.', $token)[0];
            $kid = JWTApple::jsonDecode(JWTApple::urlsafeB64Decode($header_base_64));
            $kid = $kid->kid;

            //build jwt publick key using keys from Apple endpoint (using JWK)
            try {
                $public_key = JWKApple::parseKeySet($jwks);
                $public_key = $public_key[$kid];
                $parsed_id_token = JWTApple::decode($token, $public_key, ['RS256']);
            } catch (Exception $e) {
                Log::error('Apple Sign In JWT Error', ['exception' => $e->getMessage()]);
                //we get here when there is any validation error
                return redirect()->route('profile')->withErrors(['ec5_382']);
            }

            $parsed_id_token = (array)$parsed_id_token;

            if ($parsed_id_token['email_verified'] === 'true') {
                if ($parsed_id_token['nonce'] === $nonce) {
                    //get Apple user email, always sent in the token
                    $appleUserEmail = $parsed_id_token['email'];

                    //check email is the same to the logged in user
                    if ($appleUserEmail === $this->user->email) {

                        //add Apple provider to allow loggin in with this method
                        $appleProvider = new UserProvider();
                        $appleProvider->email = $this->user->email;
                        $appleProvider->user_id = $this->user->id;
                        $appleProvider->provider = Config::get('ec5Strings.providers.apple');
                        $appleProvider->save();

                        //let's see if we have a user object
                        //Apple sends this only on fist authentication attempt
                        try {
                            $appleUser = json_decode($params['user'], true); //decode to array by passing "true"
                            $appleUserFirstName = $appleUser['name']['firstName'];
                            $appleUserLastName = $appleUser['name']['lastName'];
                        } catch (Exception $e) {
                            Log::error('Apple user object exception', ['exception' => $e->getMessage()]);
                            //if no user name found, default to Apple User
                            $appleUserFirstName = config('ec5Strings.user_placeholder.apple_first_name');
                            $appleUserLastName = config('ec5Strings.user_placeholder.apple_last_name');
                        }

                        //if user object and the current user does not have a name, update
                        if ($this->user->name === config('ec5Strings.user_placeholder.passwordless_first_name')) {
                            if ($appleUser) {
                                $this->updateUserDetailsWithApple($appleUserFirstName, $appleUserLastName);
                            }
                        }

                        if ($this->user->name === config('ec5Strings.user_placeholder.apple_first_name')) {
                            if ($appleUser) {
                                $this->updateUserDetailsWithApple($appleUserFirstName, $appleUserLastName);
                            }
                        }

                        //redirect with success message
                        session()->forget('nonce');
                        return redirect()->route('profile')
                            ->with([
                                'message' => 'ec5_388',
                            ]);
                    }
                    //users do not match, error
                    return redirect()->route('profile')->withErrors(['ec5_387']);
                }
            }
        } catch (Exception $e) {
            Log::error('Apple Connect Web Exception: ', [
                'exception' => $e->getMessage()
            ]);
        }
        return redirect()->route('profile')->withErrors(['ec5_386']);
    }

    //after 5 failed login attempts, users need to wait 10 minutes
    protected function hasTooManyLoginAttempts(Request $request)
    {
        return $this->limiter()->tooManyAttempts(
            $this->throttleKey($request),
            5,
            10
        );
    }

    private function isLocalUnverified($user)
    {
        return $user->provider === Config::get('ec5Strings.providers.local') && $user->state === Config::get('ec5Strings.user_state.unverified');
    }

    private function isLocalActive($user)
    {
        return $user->provider === Config::get('ec5Strings.providers.local') && $user->state === Config::get('ec5Strings.user_state.active');
    }

    private function isLocal($user)
    {
        return $user->provider === Config::get('ec5Strings.providers.local');
    }

    private function updateUserDetailsWithGoogle($googleUser)
    {
        if (isset($googleUser->user['given_name'])) {
            $this->user->name = $googleUser->user['given_name'];
        }
        if (isset($googleUser->user['family_name'])) {
            $this->user->last_name = $googleUser->user['family_name'];
        }
        if (isset($googleUser->avatar)) {
            $this->user->avatar = isset($googleUser->avatar);
        }
        $this->user->save();
    }

    private function updateUserDetailsWithApple($appleUserFirstName, $appleUserLastName)
    {
        $this->user->name = $appleUserFirstName;
        $this->user->last_name = $appleUserLastName;
        $this->user->save();
    }
}
