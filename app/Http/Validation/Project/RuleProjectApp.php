<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleProjectApp extends ValidationBase
{

    protected $rules = [
        'application_name' => 'required|min:3|max:50|alpha_num_under_spaces',
        // Check there isnt already an app for this project
        //'project_id' => 'unique:oauth_client_projects'
    ];

    public function __construct()
    {
       // $this->messages['unique'] = 'ec5_258';
    }

    public function additionalChecks()
    {
        //
    }

}
