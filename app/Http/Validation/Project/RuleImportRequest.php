<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleImportRequest extends ValidationBase
{
    protected $rules = [
        'name' => 'required|alpha_num_under_spaces|min:3|max:50|unique:projects,name|ec5_unreserved_name',
        'file' => 'required|max:1000',
        'slug' => 'not_in:create|unique:projects,slug',
    ];

    public function __construct()
    {
        $this->messages = array_merge($this->messages, [
            'name.max' => 'ec5_208',
            'name.min' => 'ec5_209',
            'name.alpha_num_under_spaces' => 'ec5_205',
            'name.unique' => 'ec5_85',
            'name.required' => 'ec5_21',
            'file.max' => 'ec5_206'
        ]);

    }

    public function additionalChecks()
    {
        //
    }

}
