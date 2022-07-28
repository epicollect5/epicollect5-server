<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleTransferOwnership extends ValidationBase
{

    protected $rules = [
        'manager' => 'required|min:1|numeric'
    ];

    public function __construct()
    {
        $this->messages['numeric'] = 'ec5_226';
        $this->messages['required'] = 'ec5_226';
        $this->messages['min'] = 'ec5_226';
    }

    public function additionalChecks()
    {
        //
    }

}
