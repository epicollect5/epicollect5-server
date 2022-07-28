<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;

class RuleLogin extends ValidationBase
{
    protected $rules = [
        'email' => 'required',
        'password' => 'required'
    ];

    /**
     * Additional checks
     */
    public function additionalChecks()
    {
        //
    }
}
