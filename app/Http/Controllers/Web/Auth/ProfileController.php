<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use ec5\Http\Controllers\Controller;
use ec5\Libraries\Auth\JwtApple\JWK as JWKApple;
use ec5\Libraries\Auth\JwtApple\JWT as JWTApple;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use ec5\Traits\Auth\AppleJWTHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Log;
use Throwable;

class ProfileController extends Controller
{
    use AppleJWTHandler;

    protected array $providers;
    protected User $user;
    protected string $appleProviderLabel;
    protected string $googleProviderLabel;

    public function __construct()
    {
        //get Auth in constructor using a closure, needed for 5.3+ -> t.ly/vuxc
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            $this->providers = UserProvider::where('email', $this->user->email)
                ->pluck('provider')->toArray();

            $this->appleProviderLabel = config('epicollect.strings.providers.apple');
            $this->googleProviderLabel = config('epicollect.strings.providers.google');

            return $next($request);
        });
    }

    /**
     * Show the application login form.
     *
     */
    public function show(Request $request)
    {
        $nonce = csrf_token();
        session(['nonce' => $nonce]);

        return view('auth.profile', [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'providers' => $this->providers,
            'nonce' => $nonce,
            'auth_methods' => config('auth.auth_methods')
        ]);
    }

    /**
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

    public function connectGoogle()
    {
        //local users cannot connect Google
        if ($this->isLocal($this->user)) {
            return redirect()->back();
        }

        return Socialite::with('google')
            ->with(['prompt' => 'select_account']) //todo not sure we might remove this
            ->redirectUrl(config('auth.google.connect_redirect_uri'))
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
     * @return RedirectResponse
     */
    public function handleGoogleConnectCallback()
    {
        try {
            // Find the Google user
            $googleUser = Socialite::with('google')
                ->redirectUrl(config('auth.google.connect_redirect_uri'))
                ->user();

            // If we found a Google user
            if ($googleUser) {
                //check email is the same to the logged-in user
                if ($googleUser->email === $this->user->email) {
                    //add google provider, to allow logging in with both methods
                    $googleProvider = new UserProvider();
                    $googleProvider->email = $this->user->email;
                    $googleProvider->user_id = $this->user->id;
                    $googleProvider->provider = config('epicollect.strings.providers.google');

                    //if name is either Apple User or User, update user
                    if ($this->user->name === config('epicollect.mappings.user_placeholder.apple_first_name')) {
                        $this->updateUserDetailsWithGoogle($googleUser);
                    }
                    if ($this->user->name === config('epicollect.mappings.user_placeholder.passwordless_first_name')) {
                        $this->updateUserDetailsWithGoogle($googleUser);
                    }

                    $googleProvider->save();

                    //redirect with a success message
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
        } catch (Throwable $e) {
            Log::error('Google Connect Web Exception: ', [
                'exception' => $e->getMessage()
            ]);
            return redirect()->route('profile')->withErrors(['ec5_369']);
        }
    }

    //this is going to be a post request as a callback by Apple
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

            //get public keys from Apple endpoint
            list($jwks, $kid) = $this->getPublicKeysFromAppleEndpoint($token);

            //build jwt public key using keys from Apple endpoint (using JWK)
            try {
                $public_key = JWKApple::parseKeySet($jwks);
                $public_key = $public_key[$kid];
                $parsed_id_token = JWTApple::decode($token, $public_key, ['RS256']);
            } catch (Throwable $e) {
                Log::error('Apple Sign In JWT Error', ['exception' => $e->getMessage()]);
                //we get here when there is any validation error
                return redirect()->route('profile')->withErrors(['ec5_382']);
            }

            $parsed_id_token = (array)$parsed_id_token;

            //catching error when email is missing from payload
            if (!isset($parsed_id_token['email'])) {
                Log::error(__METHOD__ . ' failed.', ['Apple Connect' => 'email missing in payload']);
                return redirect()->route('profile')->withErrors(['ec5_386']);
            }

            if ($parsed_id_token['nonce'] === $nonce) {
                //get Apple user email, always sent in the token
                $appleUserEmail = $parsed_id_token['email'];

                //check email is the same to the logged-in user
                if ($appleUserEmail === $this->user->email) {

                    //add Apple provider to allow login in with this method
                    $appleProvider = new UserProvider();
                    $appleProvider->email = $this->user->email;
                    $appleProvider->user_id = $this->user->id;
                    $appleProvider->provider = config('epicollect.strings.providers.apple');
                    $appleProvider->save();

                    //let's see if we have a user object
                    //Apple sends this only on fist authentication attempt
                    try {
                        $appleUser = json_decode($params['user'], true); //decode to array by passing "true"
                        $appleUserFirstName = $appleUser['name']['firstName'];
                        $appleUserLastName = $appleUser['name']['lastName'];
                    } catch (Throwable $e) {
                        Log::info('Apple user object exception, existing user, use defaults', ['exception' => $e->getMessage()]);
                        //if no user name found, default to Apple User
                        $appleUserFirstName = config('epicollect.mappings.user_placeholder.apple_first_name');
                        $appleUserLastName = config('epicollect.mappings.user_placeholder.apple_last_name');
                    }

                    //if user object and the current user does not have a name, update
                    if ($this->user->name === config('epicollect.mappings.user_placeholder.passwordless_first_name')) {
                        if ($appleUser) {
                            $this->updateUserDetailsWithApple($appleUserFirstName, $appleUserLastName);
                        }
                    }

                    if ($this->user->name === config('epicollect.mappings.user_placeholder.apple_first_name')) {
                        if ($appleUser) {
                            $this->updateUserDetailsWithApple($appleUserFirstName, $appleUserLastName);
                        }
                    }

                    //redirect with a success message
                    session()->forget('nonce');
                    return redirect()->route('profile')
                        ->with([
                            'message' => 'ec5_388',
                        ]);
                }
                //users do not match, error
                return redirect()->route('profile')->withErrors(['ec5_387']);
            }
        } catch (Throwable $e) {
            Log::error('Apple Connect Web Exception: ', [
                'exception' => $e->getMessage()
            ]);
        }
        return redirect()->route('profile')->withErrors(['ec5_386']);
    }

    private function isLocal($user)
    {
        return $user->provider === config('epicollect.strings.providers.local');
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
