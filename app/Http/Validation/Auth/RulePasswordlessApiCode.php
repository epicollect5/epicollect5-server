<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;

class RulePasswordlessApiCode extends ValidationBase
{
    protected array $rules = [
        'email' => 'required|email'
    ];

    public function __construct()
    {
        $this->messages['email.required'] = 'ec5_21';
        $this->messages['email.email'] = 'ec5_42';
    }
}
