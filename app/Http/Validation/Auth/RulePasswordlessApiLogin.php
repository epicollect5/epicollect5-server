<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;

class RulePasswordlessApiLogin extends ValidationBase
{
    protected array $rules = [
        'email' => 'required|email',
        'code' => 'required|string|size:6|regex:/^[0-9]+$/'
    ];

    public function __construct()
    {
        $this->messages['email.required'] = 'ec5_21';
        $this->messages['email.email'] = 'ec5_42';
    }
}
