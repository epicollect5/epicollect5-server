<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleProjectDefinitionDetails extends ValidationBase
{
    protected $rules = [
        'description' => 'ec5_no_html|between:3,3000',
        'small_description' => 'required|ec5_no_html|between:3,100',
        'logo_url' => 'mimes:jpeg,jpg,gif,png|max:5000',
        'logo_width' => 'integer|max:4096',
        'logo_height' => 'integer|max:4096'
    ];

    protected $messages = [
        'integer' => 'ec5_27',
        'required' => 'ec5_21',
        'max' => 'ec5_206',
        'mimes' => 'ec5_81',
        'between' => 'ec5_28',
        'ec5_no_html' => 'ec5_220'
    ];

    /**
     *
     */
    public function additionalChecks()
    {
        //
    }

}
