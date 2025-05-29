<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use ec5\Http\Validation\Auth\RuleLogin as LoginValidator;
use ec5\Models\User\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

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
     * @throws Throwable
     */
    public function authenticate(Request $request, LoginValidator $validator)
    {
        session(['url.intended' => route('admin-stats')]);

        $input = $request->all();
        $validator->validate($input);
        if ($validator->hasErrors()) {
            // Redirect back if errors
            return redirect()->back()->withErrors($validator->errors());
        }

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return view('auth.login')->withErrors(['ec5_37']);
        }

        // Common credentials
        $credentials = array(
            'email' => $input['email'],
            'password' => $input['password']
        );

        // Attempt to login superadmin or admin user
        if ($this->validateAdminCredentials($credentials)) {
            return $this->dispatchAuthToken($input['email']);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to log in and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return redirect()->route('login-admin')
        ->withInput($request->only('email', 'remember'))
        ->withErrors(['ec5_36']);
    }

    private function validateAdminCredentials($credentials)
    {
        if (Auth::validate($credentials)) {
            $user = User::where('email', $credentials['email'])->first();
            // Server role must be 'admin' or higher (extra check you never know)
            if ($user->server_role === 'basic') {
                return false;
            }
            return true;
        }
        return false;
    }
}
