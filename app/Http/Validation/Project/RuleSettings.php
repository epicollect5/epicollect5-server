<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleSettings extends ValidationBase
{
    protected $rules = [
        'access' => '',
        'visibility' => '',
        'status' => '',
        'category' => '',
        'app_link_visibility' => ''
    ];

    /**
     * RuleSettings constructor.
     */
    public function __construct()
    {
        $this->rules['access'] = 'sometimes|in:' . implode(',', array_keys(config('epicollect.strings.projects_access')));
        $this->rules['visibility'] = 'sometimes|in:' . implode(',', array_keys(config('epicollect.strings.projects_visibility')));
        $this->rules['status'] = 'sometimes|in:' . implode(',', array_keys(config('epicollect.strings.projects_status_all')));
        $this->rules['category'] = 'sometimes|in:' . implode(',', array_keys(config('epicollect.strings.project_categories')));
        $this->rules['app_link_visibility'] = 'sometimes|in:' . implode(',', array_keys(config('epicollect.strings.app_link_visibility')));
    }


    /**
     *
     */
    public function additionalChecks()
    {
        //
    }

}
