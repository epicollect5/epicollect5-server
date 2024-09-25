<?php

namespace ec5\Http\Controllers\Web\Auth;

class LoginController extends AuthController
{
    /*
    | This controller handles the login page.
    */

    //Show the application login form.
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
}
