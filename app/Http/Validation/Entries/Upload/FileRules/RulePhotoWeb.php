<?php

namespace ec5\Http\Validation\Entries\Upload\FileRules;

use ec5\Http\Validation\ValidationBase;

class RulePhotoWeb extends ValidationBase
{
    protected array $rules = [
        'file' => 'required|mimes:jpeg,jpg,png|max:10000|dimensions:max_width=4096,max_height=4096',
       // 'width' => 'required|numeric|max:4096',
       // 'height' => 'required|numeric|max:4096'
    ];

    protected array $messages = [
        'required' => 'ec5_21',
        'mimes' => 'ec5_81',
        'max' => 'ec5_206',
        'dimensions' => 'ec5_332'
    ];

    /**
     *
     */
    public function additionalChecks()
    {
        //
    }

}
