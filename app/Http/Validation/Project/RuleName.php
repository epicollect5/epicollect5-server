<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleName extends ValidationBase
{
    protected array $rules = [
        'name' => 'required|alpha_num_under_spaces|min:3|max:50',
        'slug' => 'required|not_in:create|unique_except_archived:projects,slug'
    ];

    public function __construct()
    {
        $this->messages['unique_except_archived'] = 'ec5_85';
    }

    public function additionalChecks()
    {
        //
    }

}
