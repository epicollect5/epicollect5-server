<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Config;

class RuleProjectDefinitionDetails extends ValidationBase
{
    protected $rules = [
        'description' => 'ec5_no_html|between:3,3000',
        'small_description' => 'required|ec5_no_html|between:15,100',
        'logo_url' => 'mimes:jpeg,jpg,gif,png|max:5000',
        'logo_width' => 'integer|max:4096',
        'logo_height' => 'integer|max:4096'
    ];

    protected $messages = [
        'integer' => 'ec5_27',
        'required' => 'ec5_21',
        'max' => 'ec5_206',
        'mimes' => 'ec5_81',
        'description.between' => 'ec5_393',
        'small_description.between' => 'ec5_394',
        'ec5_no_html' => 'ec5_220'
    ];

    public function __construct()
    {
        //set up error messages
        $projectSmallDescMinLength = config('epicollect.limits.project.small_desc.min');
        $projectSmallDescMaxLength = config('epicollect.limits.project.small_desc.max');
        $projectDescriptionMinLength = config('epicollect.limits.project.description.min');
        $projectDescriptionMaxLength = config('epicollect.limits.project.description.max');

        $this->messages['description.between'] = trans('status_codes.ec5_393', [
            'min' => $projectDescriptionMinLength,
            'max' => $projectDescriptionMaxLength
        ]);

        $this->messages['small_description.between'] = trans('status_codes.ec5_394', [
            'min' => $projectSmallDescMinLength,
            'max' => $projectSmallDescMaxLength
        ]);
    }

    /**
     *
     */
    public function additionalChecks()
    {
        //
    }

}
