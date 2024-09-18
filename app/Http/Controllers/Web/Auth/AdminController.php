<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use ec5\Http\Validation\Auth\RuleLogin as LoginValidator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminController extends AuthController
{
    /*
    |
    | This controller handles the authentication of Admin users.
    |
    */
    use AuthenticatesUsers;

    public function __construct()
    {
        parent::__construct();
    }

    public function show()
    {
        return view('admin.login');
    }

    /**
     * Handle an admin login request to the application.
     *
     * @param Request $request
     * @param LoginValidator $validator
     * @return Factory|View|Application|RedirectResponse|\Illuminate\View\View
     */
    public function authenticate(Request $request, LoginValidator $validator)
    {
        $input = $request->all();
        $validator->validate($input);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($lockedOut = $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return view('auth.login')->withErrors(['ec5_37']);
        }

        // Common credentials
        $credentials = array(
            'email' => $input['email'],
            'password' => $input['password'],
            'state' => 'active'
        );

        // Attempt to login superadmin user
        $credentials['server_role'] = 'superadmin';
        if ($this->attemptAdminLogin($credentials)) {
            // Redirect to admin page
            return redirect()->route('admin-stats');
        }

        //attempt to log in admin users
        $credentials['server_role'] = 'admin';
        if ($this->attemptAdminLogin($credentials)) {
            // Redirect to admin page
            return redirect()->route('admin-stats');
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to log in and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        if (!$lockedOut) {
            $this->incrementLoginAttempts($request);
        }

        return redirect()->route('login-admin')
        ->withInput($request->only('email', 'remember'))
        ->withErrors(['ec5_36']);
    }

    private function attemptAdminLogin($credentials)
    {

        if (Auth::attempt($credentials, false)) {
            $user = Auth::getLastAttempted();
            // Server role must be 'admin' or higher (extra check you never know)
            if ($user->server_role === 'basic') {
                Auth::logout();
                return false;
            }
            // Log admin or superadmin user in
            Auth::login($user, false);
            // Redirect to admin page
            return true;
        }
        return false;
    }
}
