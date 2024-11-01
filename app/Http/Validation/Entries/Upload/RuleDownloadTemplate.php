<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;

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
        $cookieName = config('epicollect.setup.cookies.download_entries');
        $this->rules[$cookieName] = 'required';//this is the cookie name
    }

    public function additionalChecks(ProjectDTO $project, $options)
    {
        //
    }
}
