<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleEmail extends ValidationBase
{
    protected $rules = [
        'email' => 'required|email'
    ];
}
