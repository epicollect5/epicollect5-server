<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;
use Illuminate\Support\Str;
use League\Csv\Reader;

class RulePasswordlessApiCode extends ValidationBase
{
    protected $rules = [
        'email' => 'required|email'
    ];

    public function __construct()
    {
        $this->messages['email.required'] = 'ec5_21';
        $this->messages['email.email'] = 'ec5_42';
    }
}
