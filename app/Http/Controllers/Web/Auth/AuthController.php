<?php

namespace ec5\Http\Controllers\Web\Auth;

use ec5\Http\Controllers\Controller;
use ec5\Models\User\User;
use ec5\Models\User\UserPasswordlessApi;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Log;
use View;

class AuthController extends Controller
{
    /*
    | This controller is the base for the authentication of users.
    */

    use AuthenticatesUsers;

    protected string $redirectTo = '/';
    protected mixed $authMethods = [];
    protected string $appleProviderLabel;
    protected string $googleProviderLabel;
    protected string $localProviderLabel;
    protected string $ldapProviderlabel;
    protected string $passwordlessProviderLabel;
    protected bool $isAuthWebEnabled;
    protected bool $isAuthApiLocalEnabled;

    public function __construct()
    {
        // Determine which authentication methods are available
        $this->authMethods = config('auth.auth_methods');

        //set providers values
        $this->appleProviderLabel = config('epicollect.strings.providers.apple');
        $this->googleProviderLabel = config('epicollect.strings.providers.google');
        $this->localProviderLabel = config('epicollect.strings.providers.local');
        $this->ldapProviderlabel = config('epicollect.strings.providers.ldap');
        $this->passwordlessProviderLabel = config('epicollect.strings.providers.passwordless');

        // Always pass the authentication method variables to the login view
        View::composer('auth.login', function ($view) {
            $view->with('authMethods', $this->authMethods);
            // Set column size for responsive layout
            $colSize = 12 / (count($this->authMethods) > 0 ? count($this->authMethods) : 1);
            $colSize = ($colSize == 1) ? 2 : $colSize;
            $view->with('colSize', $colSize);
        });

        $this->isAuthApiLocalEnabled = config('auth.auth_api_local_enabled');
        $this->isAuthWebEnabled = config('auth.auth_web_enabled');
    }

    //after 5 failed login attempts, users need to wait 10 minutes
    protected function hasTooManyLoginAttempts(Request $request)
    {
        return $this->limiter()->tooManyAttempts(
            $this->throttleKey($request),
            5
        );
    }

    protected function validateAppleOrGoogleUserWeb($email, $code, $provider): User|RedirectResponse
    {
        //get token from db for comparison
        $userPasswordless = UserPasswordlessApi::where('email', $email)->first();

        //Does the email exists?
        if ($userPasswordless === null) {
            Log::error('Error validating passwordless code', ['error' => 'Email does not exist']);
            return redirect()->route('verification-code')
                ->with([
                    'email' => $email,
                    'provider' => $provider
                ])
                ->withErrors(['ec5_378']);
        }

        //check if the code is valid
        if (!$userPasswordless->isValidCode($code)) {
            Log::error('Error validating passwordless code', ['error' => 'Code not valid']);
            return redirect()->route('verification-code')
                ->with([
                    'email' => $email,
                    'provider' => $provider
                ])
                ->withErrors(['ec5_378']);
        }

        //code is valid, remove it
        $userPasswordless->delete();

        //find the existing user
        $user = User::where('email', $email)->first();
        if ($user === null) {
            //this should never happen, but no harm in checking
            return redirect()->route('verification-code')
                ->with([
                    'email' => $email,
                    'provider' => $provider
                ])
                ->withErrors(['ec5_34']);
        }

        return $user;
    }
}
