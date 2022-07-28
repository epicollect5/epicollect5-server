<?php

namespace ec5\Http\Validation\Admin;

use ec5\Http\Validation\ValidationBase;

class RuleProjectRole extends ValidationBase
{
    protected $rules = [
        'role' => 'in:creator,manager,curator,collector',
        'project_id' => 'required|numeric'
    ];

}
