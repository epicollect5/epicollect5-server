<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\Models\Projects\Project;
use ec5\Http\Validation\ValidationBase;
use Config;

class RuleDownloadTemplate extends ValidationBase
{
    protected $rules = [
        'map_index' => 'required|numeric|min:0',
        'form_index' => 'required|numeric|min:0|max:4',
        'branch_ref' => 'present|string',
        'format' => 'required|in:csv',
        'filename' => 'required'
    ];

    public function __construct()
    {
        $cookieName = config('epicollect.mappings.cookies.download-entries');
        $this->rules[$cookieName] = 'required';//this is the cookie name
    }

    public function additionalChecks(Project $project, $options)
    {
        //
    }
}
