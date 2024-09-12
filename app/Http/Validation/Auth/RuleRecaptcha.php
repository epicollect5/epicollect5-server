<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;

class RuleRecaptcha extends ValidationBase
{
    protected $rules = [
        'success' => 'boolean',
        'challenge_ts' => 'present',
        'hostname' => 'present',
        'score' => 'numeric|min:0.5',
        'action' => 'in:signup,forgot,passwordless'
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
