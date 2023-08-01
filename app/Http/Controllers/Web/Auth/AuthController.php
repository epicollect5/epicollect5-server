<?php

namespace ec5\Http\Controllers\Web\Auth;

use Illuminate\Support\Str;
use ec5\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use ec5\Models\Eloquent\UserProvider;
use Illuminate\Http\Request;
use Config;
use View;
use Auth;

class AuthController extends Controller
{
    /*
    |
    | This controller is the base for the authentication of users.
    |
    */

    use AuthenticatesUsers;
    protected $redirectTo = '/';
    protected $adminRedirectPath = '/admin';
    protected $authMethods = [];
    protected $appleProviderLabel;
    protected $googleProviderLabel;
    protected $localProviderLabel;
    protected $ldapProviderlabel;
    protected $passwordlessProviderLabel;
    protected $isAuthWebEnabled;

    public function __construct()
    {
        // Determine which authentication methods are available
        $this->authMethods = Config::get('auth.auth_methods');

        //set providers values
        $this->appleProviderLabel = Config::get('ec5Strings.providers.apple');
        $this->googleProviderLabel = Config::get('ec5Strings.providers.google');
        $this->localProviderLabel = Config::get('ec5Strings.providers.local');
        $this->ldapProviderlabel = Config::get('ec5Strings.providers.ldap');
        $this->passwordlessProviderLabel = Config::get('ec5Strings.providers.passwordless');

        // Always pass the authentication method variables to the login view
        View::composer('auth.login', function ($view) {
            $view->with('authMethods', $this->authMethods);
            // Set column size for responsive layout
            $colSize = 12 / (count($this->authMethods) > 0 ? count($this->authMethods) : 1);
            $colSize = ($colSize == 1) ? 2 : $colSize;
            $view->with('colSize', $colSize);
        });

        $this->isAuthApiLocalEnabled = Config::get('auth.auth_api_local_enabled');
        $this->isAuthWebEnabled = Config::get('auth.auth_web_enabled');
    }

    /**
     * Show the application login form.
     *
     * @return View
     */
    public function show()
    {
        if ($this->isAuthWebEnabled) {
            //get intended url for redirection 
            //(skip passwordless token/web routes as they are post only)
            switch (url()->previous()) {
                case route('passwordless-auth-web'):
                case route('passwordless-token-web'):
                    //send user to home page
                    session()->put('url.intended', route('home'));
                    break;
                default:
                    session()->put('url.intended', url()->previous());
            }

            session(['nonce' => csrf_token()]);
            return view('auth.login');
        }
        return redirect()->route('home');
    }

    /**
     * Log the user out of the application.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function logout(Request $request)
    {
        $backlink = url()->previous();
        Auth::logout();
        $request->session()->flush();
        $request->session()->regenerate();

        //if we are logging out from the dataviewer, send user back there
        // 1 - private project -> login + dataviewer
        // 2 - public project -> dataviewer (without add entry button)
        $parts = explode('/', $backlink);
        \Log::info('url parts ->',  ['parts' => $parts]);
        //check for dataviewer url segments
        if (end($parts) === 'data' || end($parts) === 'data?restore=1') {
            array_pop($parts);
            $projectSlug = end($parts);
            return redirect()->route('dataviewer', ['project_slug' => $projectSlug]);
        }

        //handle PWA (add-entry)
        if (Str::startsWith(end($parts), 'add-entry')) {
            array_pop($parts);
            $projectSlug = end($parts);
            return redirect()->route('data-editor-add', ['project_slug' => $projectSlug]);
        }
        //todo: handle PWA (edit-entry)
        //I guess this is not needed
        // if (Str::startsWith(end($parts), 'edit-entry')) {
        //     array_pop($parts);
        //     $projectSlug = end($parts);
        //     return redirect()->route('data-editor-edit', ['project_slug' => $projectSlug]);
        // }

        return redirect()->route('home');
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

    protected function isLocalUnverified($user)
    {
        $providers = UserProvider::where('email', $user->email)->pluck('provider')->toArray();

        return in_array(Config::get('ec5Strings.providers.local'), $providers) && $user->state === Config::get('ec5Strings.user_state.unverified');
    }

    //does the user have a local account and active?
    protected function isLocalActive($user)
    {
        $providers = UserProvider::where('email', $user->email)->pluck('provider')->toArray();

        return in_array(Config::get('ec5Strings.providers.local'), $providers) && $user->state === Config::get('ec5Strings.user_state.active');
    }
}
