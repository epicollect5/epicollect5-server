<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use ec5\Libraries\Utilities\Common;

class RuleCreateRequest extends ValidationBase
{
    protected array $rules = [
        'name' => 'required|alpha_num_under_spaces|min:3|max:50|ec5_unreserved_name|unique_except_archived:projects,name',
        'slug' => 'required|not_in:create|unique_except_archived:projects,slug',
        'small_description' => 'required|min:15|max:100',
        'access' => 'required|in:private,public'
    ];

    public function __construct()
    {
        //set up error messages
        $projectNameMinLength = config('epicollect.limits.project.name.min');
        $projectSmallDescMinLength = config('epicollect.limits.project.small_desc.min');
        $projectSmallDescMaxLength = config('epicollect.limits.project.small_desc.max');
        $projectNameMaxLength = config('epicollect.limits.project.name.max');
        $formNameMaxLength = config('epicollect.limits.project.form.name.max');

        $this->rules['form_name'] = 'required|alpha_num_under_spaces|min:1|max:' . $formNameMaxLength;
        $this->messages['name.min'] = Common::configWithParams('epicollect.codes.ec5_349', ['min' => $projectNameMinLength]);
        $this->messages['name.max'] = Common::configWithParams('epicollect.codes.ec5_350', ['max' => $projectNameMaxLength]);
        $this->messages['small_description.min'] = Common::configWithParams('epicollect.codes.ec5_351', ['min' => $projectSmallDescMinLength]);
        $this->messages['small_description.max'] = Common::configWithParams('epicollect.codes.ec5_352', ['max' => $projectSmallDescMaxLength]);

        $this->messages['unique'] = 'ec5_85';
        $this->messages['unique_except_archived'] = 'ec5_85';
    }
}
