<?php

namespace ec5\Http\Validation\Admin;

use ec5\Http\Validation\ValidationBase;

class RuleProjectRole extends ValidationBase
{
    protected array $rules = [
        'role' => 'in:creator,manager,curator,collector',
        'project_id' => 'required|numeric'
    ];

}
