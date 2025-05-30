<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;

class RuleUploadHeaders extends ValidationBase
{
    /**F
     * @var array
     */
    protected array $rules = [
        'map_index' => 'required|numeric|min:0',
        'form_index' => 'required|numeric|min:0|max:4',
        'branch_ref' => 'present|string',
        'format' => 'required|in:json'
    ];

    /**
     * @param ProjectDTO $project
     * @param $options
     */
    public function additionalChecks(ProjectDTO $project, $options)
    {
        //
    }
}
