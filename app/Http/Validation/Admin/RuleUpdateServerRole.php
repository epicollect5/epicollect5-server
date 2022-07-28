<?php

namespace ec5\Http\Validation\Admin;

class RuleUpdateServerRole extends RulePermissionsBase
{
    protected $rules = [
        'server_role' => 'required|in:basic,admin'
    ];

}
