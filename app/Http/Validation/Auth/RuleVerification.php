<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;
use Illuminate\Support\Str;
use League\Csv;
use League\Csv\Reader;

class RuleVerification extends ValidationBase
{

    protected $rules = [
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
