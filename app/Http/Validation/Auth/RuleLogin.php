<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;

class RuleLogin extends ValidationBase
{
    protected array $rules = [
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
