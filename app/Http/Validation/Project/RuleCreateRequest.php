<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Config;

class RuleCreateRequest extends ValidationBase
{

    protected $rules = [
        'name' => 'required|alpha_num_under_spaces|min:3|max:50|ec5_unreserved_name',
        'slug' => 'required|not_in:create|unique:projects',
        'small_description' => 'required|min:15|max:100',
        'access' => 'required|in:private,public'
    ];


    public function __construct()
    {
        //set up error messages
        $projectNameMinLength = Config::get('ec5Limits.project.name.min');
        $projectSmallDescMinLength = Config::get('ec5Limits.project.small_desc.min');
        $projectSmallDescMaxLength = Config::get('ec5Limits.project.small_desc.max');
        $projectNameMaxLength = Config::get('ec5Limits.project.name.max');
        $formNameMaxLenght = Config::get('ec5Limits.form_name_limit');

        $this->rules['form_name'] ='required|alpha_num_under_spaces|min:1|max:' . $formNameMaxLenght;

        $this->messages['name.min'] = trans('status_codes.ec5_349', ['min' => $projectNameMinLength]);
        $this->messages['name.max'] = trans('status_codes.ec5_350', ['max' => $projectNameMaxLength]);
        $this->messages['small_description.min'] = trans('status_codes.ec5_351', ['min' => $projectSmallDescMinLength]);
        $this->messages['small_description.max'] = trans('status_codes.ec5_352', ['max' => $projectSmallDescMaxLength]);

        $this->messages['unique'] = 'ec5_85';
    }
}
