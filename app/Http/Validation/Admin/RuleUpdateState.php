<?php

namespace ec5\Http\Validation\Admin;

class RuleUpdateState extends RulePermissionsBase
{
    protected $rules = [
        'state' => 'required|in:active,disabled'
    ];
    
}
