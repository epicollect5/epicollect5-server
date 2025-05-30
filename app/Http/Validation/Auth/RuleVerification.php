<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;

class RuleVerification extends ValidationBase
{
    protected array $rules = [
        'code' => 'required|string|size:6|regex:/^[0-9]+$/',
    ];

    public function __construct()
    {
        $this->messages['code.*'] = 'ec5_87';
    }

    /**
     * Additional checks
     */
}
