<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleName extends ValidationBase
{

    protected $rules = [
        'name' => 'required|alpha_num_under_spaces|min:3|max:50',
        'slug' => 'required|not_in:create|unique:projects',
    ];

    public function __construct()
    {
        $this->messages['unique'] = 'ec5_85';
    }

    public function additionalChecks()
    {
        //
    }

}
