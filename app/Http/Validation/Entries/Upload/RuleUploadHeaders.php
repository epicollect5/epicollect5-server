<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\Models\Projects\Project;
use ec5\Http\Validation\ValidationBase;
use Config;

class RuleUploadHeaders extends ValidationBase
{
    /**F
     * @var array
     */
    protected $rules = [
        'map_index' => 'required|numeric|min:0',
        'form_index' => 'required|numeric|min:0|max:4',
        'branch_ref' => 'present|string',
        'format' => 'required|in:json'
    ];

    /**
     * @param Project $project
     * @param $options
     */
    public function additionalChecks(Project $project, $options)
    {
        //
    }
}
