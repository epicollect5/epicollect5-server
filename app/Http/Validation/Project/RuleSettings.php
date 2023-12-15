<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Config;

class RuleSettings extends ValidationBase
{
    protected $rules = [

        'access' => '',
        'visibility' => '',
        'status' => '',
        'category' => ''
    ];

    /**
     * RuleSettings constructor.
     */
    public function __construct()
    {
        $this->rules['access'] = 'sometimes|in:' . implode(',', array_keys(config('epicollect.strings.projects_access')));
        $this->rules['visibility'] = 'sometimes|in:' . implode(',', array_keys(config('epicollect.strings..projects_visibility')));
        $this->rules['status'] = 'sometimes|in:' . implode(',', array_keys(config('epicollect.strings..projects_status_all')));
        $this->rules['category'] = 'sometimes|in:' . implode(',', array_keys(config('epicollect.strings..project_categories')));
    }


    /**
     *
     */
    public function additionalChecks()
    {
        //
    }

}
