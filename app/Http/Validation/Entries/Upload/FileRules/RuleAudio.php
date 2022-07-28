<?php

namespace ec5\Http\Validation\Entries\Upload\FileRules;

use ec5\Http\Validation\ValidationBase;

class RuleAudio extends ValidationBase
{
    protected $rules = [
        'file' => 'required|mimes:mp4,wav|max:100000'
    ];

    /**
     * RuleAudio constructor.
     */
    public function __construct()
    {
        $this->messages['mimes'] = 'ec5_81';
        $this->messages['max'] = 'ec5_206';
    }
    /**
     *
     */
    public function additionalChecks()
    {

    }

}
