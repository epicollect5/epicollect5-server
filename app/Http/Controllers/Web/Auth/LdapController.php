<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use ec5\Models\User\User;
use ec5\Services\UserService;
use Illuminate\Http\Request;
use Ldap;


class LdapController extends AuthController
{
    /*
    |
    | This controller handles the authentication of LDAP users.
    |
    */

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handle an ldap login request to the application.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function authenticate(Request $request)
    {
        $remember = false;

        // Check this auth method is allowed
        if (in_array('ldap', $this->authMethods)) {

            // Throttle local login attempts
            if ($lockedOut = $this->hasTooManyLoginAttempts($request)) {
                $this->fireLockoutEvent($request);
                return view('auth.login')->withErrors(['ec5_37']);
            }

            // check if there were any errors while connecting
            if (Ldap::hasErrors()) {
                return view('auth.login')
                    ->withErrors(Ldap::errors());
            }

            // Attempt to find the ldap user
            $ldapUser = Ldap::retrieveByCredentials($request->only('username', 'password'));

            // If we found and verified the user, login
            if ($ldapUser) {
                // Check user exists and is active
                $userModel = new User();
                $user = UserService::findOrCreateLdapUser($ldapUser);
                if ($user) {
                    // Login user
                    Auth::login($user, $remember);
                    return $this->sendLoginResponse($request);
                }
            }
            // Check for any further errors
            if (Ldap::hasErrors()) {
                return view('auth.login')
                    ->withErrors(Ldap::errors());
            }

            // Could not authenticate
            return view('auth.login')->withErrors(['ec5_33']);

        }
        // Auth method not allowed
        return view('auth.login')->withErrors(['ec5_55']);
    }
}
