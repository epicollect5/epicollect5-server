<?php

namespace ec5\Http\Controllers\Web\Auth;

use Illuminate\Foundation\Auth\ResetsPasswords;
use ec5\Http\Controllers\Controller;

class PasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior.
    |
    */

    use ResetsPasswords;

    /**
     * Create a new password controller instance.
     *
     */
    public function __construct()
    {
        $this->middleware('guest');
    }
}
