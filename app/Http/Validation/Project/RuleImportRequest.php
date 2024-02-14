<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Config;
use ec5\Libraries\Utilities\Common;

class RuleImportRequest extends ValidationBase
{
    protected $rules = [
        'name' => 'required|alpha_num_under_spaces|min:3|max:50|unique_except_archived:projects,name|ec5_unreserved_name',
        'file' => 'required|max:1000',
        'slug' => 'not_in:create|unique_except_archived:projects,slug',
    ];

    public function __construct()
    {
        $projectNameMinLength = config('epicollect.limits.project.name.min');
        $projectNameMaxLength = config('epicollect.limits.project.name.max');
        $this->messages = array_merge($this->messages, [
            'name.max' => 'ec5_350',
            'name.min' => 'ec5_349',
            'name.alpha_num_under_spaces' => 'ec5_205',
            'name.unique' => 'ec5_85',
            'name.required' => 'ec5_21',
            'file.max' => 'ec5_206'
        ]);

        $this->messages['name.min'] = Common::configWithParams('epicollect.codes.ec5_349', ['min' => $projectNameMinLength]);
        $this->messages['name.max'] = Common::configWithParams('epicollect.codes.ec5_350', ['max' => $projectNameMaxLength]);
        $this->messages['unique_except_archived'] = 'ec5_85';
    }

    public function additionalChecks()
    {
        //
    }

}
