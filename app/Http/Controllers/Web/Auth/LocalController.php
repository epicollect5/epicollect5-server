<?php

namespace ec5\Http\Controllers\Web\Auth;

use Auth;
use ec5\Http\Validation\Auth\RuleLogin as LoginValidator;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use ec5\Services\User\UserService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Log;

class LocalController extends AuthController
{
    /*
    |
    | This controller handles the authentication of local users.
    |
    */
    public function __construct()
    {
        parent::__construct();
    }

    //show staff login form if local auth is enabled
    public function show()
    {
        $isLocalAuthEnabled = in_array($this->localProviderLabel, $this->authMethods, true);
        if (!$isLocalAuthEnabled) {
            return redirect()->route('home');
        }

        return view('staff.login');
    }

    /**
     * Handle a staff login request to the application
     * only if local lohins are enabled
     *
     * @param Request $request
     * @param LoginValidator $validator
     * @return Factory|View|Application|JsonResponse|RedirectResponse|\Illuminate\View\View
     */
    public function authenticate(Request $request, LoginValidator $validator)
    {
        $remember = false;

        // Check this auth method is allowed
        if (in_array('local', $this->authMethods)) {

            $input = $request->all();

            $validator->validate($input);
            if ($validator->hasErrors()) {
                // Redirect back if errors
                return redirect()->back()->withErrors($validator->errors());
            }

            // If the class is using the ThrottlesLogins trait, we can automatically throttle
            // the login attempts for this application. We'll key this by the username (email) and
            // the IP address of the client making these requests into this application.
            if ($lockedOut = $this->hasTooManyLoginAttempts($request)) {
                $this->fireLockoutEvent($request);
                return redirect()->back()->withErrors(['ec5_37']);
            }

            //check if email is whitelisted
            if (!UserService::isAuthenticationDomainAllowed($input['email'])) {
                Log::error('Email not whitelisted', ['email' => $input['email']]);
                return redirect()->back()->withErrors(['ec5_266']);
            }

            // Check credentials ie email, password
            $credentials = array(
                'email' => $input['email'],
                'password' => $input['password']
            );

            // Attempt to log the user in only if he has a local provider
            $providerLocal = UserProvider::where('email', $input['email'])
                ->where('provider', 'local')->first();
            if (!$providerLocal) {
                return view('staff.login')->withErrors(['ec5_36']);
            }

            if (Auth::attempt($credentials, $remember)) {
                //check user logged in state
                if (Auth::user()->state === config('epicollect.strings.user_state.unverified')) {
                    //verify user as credentials are correct
                    if (!$this->verifyLocalUser(Auth::user())) {
                        return redirect()->route('login')->withErrors(['ec5_389']);
                    }
                }

                return $this->sendLoginResponse($request);
            }

            // If the login attempt was unsuccessful we will increment the number of attempts
            // to login and redirect the user back to the login form. Of course, when this
            // user surpasses their maximum number of attempts they will get locked out.
            if (!$lockedOut) {
                $this->incrementLoginAttempts($request);
            }

            return view('staff.login')
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['ec5_36']);
        }

        // Auth method not allowed
        return view('staff.login')->withErrors(['ec5_55']);
    }

    private function verifyLocalUser($user)
    {
        try {
            $userModel = User::where('email', $user->email)->first();
            $userModel->state = config('epicollect.strings.user_state.active');
            return $userModel->save();
        } catch (\Exception $e) {
            \Log::error('Error verifying local user', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}
