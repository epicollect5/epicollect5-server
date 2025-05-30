<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use ec5\Libraries\Utilities\Common;

class RuleProjectDefinitionDetails extends ValidationBase
{
    //rules are set dynamically from config parameters
    protected array $rules = [];

    protected array $messages = [
        'integer' => 'ec5_27',
        'required' => 'ec5_21',
        'max' => 'ec5_206',
        'mimes' => 'ec5_81',
        'description.between' => 'ec5_393',
        'small_description.between' => 'ec5_394',
        'ec5_no_html' => 'ec5_220',
        'logo_width.max' => 'ec5_332',
        'logo_height.max' => 'ec5_332'
    ];

    public function __construct()
    {
        //set up error messages
        $projectSmallDescMinLength = config('epicollect.limits.project.small_desc.min');
        $projectSmallDescMaxLength = config('epicollect.limits.project.small_desc.max');
        $projectDescriptionMinLength = config('epicollect.limits.project.description.min');
        $projectDescriptionMaxLength = config('epicollect.limits.project.description.max');

        $logoMaxSize = config('epicollect.limits.project.logo.size', 5000); // Default to 1000 if not set
        $logoMaxWidth = config('epicollect.limits.project.logo.max_width', 4096); // Default to 4096 if not set
        $logoMaxHeight = config('epicollect.limits.project.logo.max_height', 4096); // Default to 4096 if not set

        // Build dynamic rules
        $this->rules = [
            'description' => "ec5_no_html|between:{$projectDescriptionMinLength},{$projectDescriptionMaxLength}",
            'small_description' => "required|ec5_no_html|between:{$projectSmallDescMinLength},{$projectSmallDescMaxLength}",
            'logo_url' => "mimes:jpeg,jpg,gif,png|max:{$logoMaxSize}",
            'logo_width' => "integer|max:{$logoMaxWidth}",
            'logo_height' => "integer|max:{$logoMaxHeight}",
        ];

        //show meaningful error when description is out of range
        $this->messages['description.between'] = Common::configWithParams('epicollect.codes.ec5_393', [
            'min' => $projectDescriptionMinLength,
            'max' => $projectDescriptionMaxLength
        ]);
        //show meaningful error when small description is out of range
        $this->messages['small_description.between'] = Common::configWithParams('epicollect.codes.ec5_394', [
            'min' => $projectSmallDescMinLength,
            'max' => $projectSmallDescMaxLength
        ]);

        //show meaningful error when logo file size is too large
        $this->messages['logo_url.max'] = Common::configWithParams(
            'epicollect.codes.ec5_403',
            [
            'max' => Common::roundNumber(config('epicollect.limits.project.logo.size') * 1000)
        ]
        );
    }

    /**
     *
     */
    public function additionalChecks()
    {
        //
    }

}
