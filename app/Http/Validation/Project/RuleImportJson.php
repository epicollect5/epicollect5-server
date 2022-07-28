<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleImportJson extends ValidationBase
{
    protected $rules = [
        'data' => 'required',
        'data.type' => 'required|in:project',
        'data.project' => 'required|array'
    ];

    function __construct()
    {
        $projectJsonIdSize = config('ec5Limits.project.id.size');
        $this->rules['data.id'] = 'required|size:' . $projectJsonIdSize;

        $this->messages['data.required'] = 'ec5_269';
        $this->messages['data.type.required'] = 'ec5_281';
        $this->messages['data.project.required'] = 'ec5_353';
        $this->messages['data.project.array'] = 'ec5_268';
        $this->messages['data.id.required'] = 'ec5_289';
    }

    /**
     * Additional checks
     */
    public function additionalChecks()
    {
        //
    }
}
