<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use ec5\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use View;

class AuthController extends Controller
{
    /*
    |
    | This controller is the base for the authentication of users.
    |
    */

    use AuthenticatesUsers;

    protected $redirectTo = '/';
    protected $authMethods = [];
    protected $appleProviderLabel;
    protected $googleProviderLabel;
    protected $localProviderLabel;
    protected $ldapProviderlabel;
    protected $passwordlessProviderLabel;
    protected $isAuthWebEnabled;
    protected $isAuthApiLocalEnabled;

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

    /**
     * Show the application login form.
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

            return view('auth.login', [
                'gcaptcha' => config('epicollect.setup.google_recaptcha.site_key')
            ]);
        }
        return redirect()->route('home');
    }

    /**
     * Log the user out of the application.
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
        //check for dataviewer url segments
        if (end($parts) === 'data' || end($parts) === 'data?restore=1') {
            array_pop($parts);
            $projectSlug = end($parts);
            return redirect()->route('dataviewer', ['project_slug' => $projectSlug]);
        }

        //handle PWA (add-entry)
        //todo: this is useless as after logging out I cannot add or edit an entry?
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
}
