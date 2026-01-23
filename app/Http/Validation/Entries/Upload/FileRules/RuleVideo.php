<?php

namespace ec5\Http\Validation\Entries\Upload\FileRules;

use ec5\Http\Validation\ValidationBase;

class RuleVideo extends ValidationBase
{
    protected array $rules = [
        'file' => 'required|mimetypes:video/mp4|max:500000'
    ];

    protected array $messages = [
        'required' => 'ec5_21',
        'mimetypes' => 'ec5_81',
        'max' => 'ec5_206'
    ];

    /**
     *
     */
    public function additionalChecks()
    {

    }

}
