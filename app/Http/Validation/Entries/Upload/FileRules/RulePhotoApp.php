<?php

namespace ec5\Http\Validation\Entries\Upload\FileRules;

use ec5\Http\Validation\ValidationBase;

class RulePhotoApp extends ValidationBase
{
    protected $rules = [
        // NOTE: the mime types must match the file name in RulePhotoInput
        'file' => 'required|mimes:jpeg,jpg,png|max:5000',
        'width' => 'required|numeric|max:1024',
        'height' => 'required|numeric|max:1024'
    ];

    protected $messages = [
        'required' => 'ec5_21',
        'mimes' => 'ec5_81',
        'max' => 'ec5_206'
    ];

    /**
     *
     */
    public function additionalChecks()
    {
        //
    }

}