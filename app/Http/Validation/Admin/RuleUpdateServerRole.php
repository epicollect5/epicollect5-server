<?php

namespace ec5\Http\Validation\Admin;

class RuleUpdateServerRole extends RulePermissionsBase
{
    protected array $rules = [
        'server_role' => 'required|in:basic,admin'
    ];

}
