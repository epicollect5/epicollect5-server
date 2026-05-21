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

            $nonce = csrf_token();
            session(['nonce' => $nonce]);

            //is Cloudflare Turnstile enabled
            $captchaSiteKey = '';
            $isTurnstileEnabled = config('epicollect.setup.cloudflare_turnstile.use_cloudflare_turnstile');
            if ($isTurnstileEnabled) {
                $captchaSiteKey = config('epicollect.setup.cloudflare_turnstile.site_key');
            }

            return view('auth.login', [
                'captcha' => $captchaSiteKey,
                'nonce' => $nonce,
            ]);
        }
        return redirect()->route('home');
    }
}
