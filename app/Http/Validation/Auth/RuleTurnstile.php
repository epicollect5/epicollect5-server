<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;

class RuleTurnstile extends ValidationBase
{
    protected array $rules = [
        'success' => 'boolean',
        'challenge_ts' => 'present',
        'hostname' => 'present',
        'error-codes' => 'array'
    ];

    public function __construct()
    {
        $this->messages['*.*'] = 'ec5_380';
    }

    public function additionalChecks($inputs): void
    {
        if ($inputs['success'] !== true) {
            $this->addAdditionalError('captcha', 'ec5_380');
        }
    }
}