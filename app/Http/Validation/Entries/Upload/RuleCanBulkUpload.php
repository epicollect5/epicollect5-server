<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;

class RuleCanBulkUpload extends ValidationBase
{
    /**F
     * @var array
     */
    protected array $rules = [

    ];

    public function __construct()
    {
        $canBulkUploadEnums = array_keys(config('epicollect.strings.can_bulk_upload'));
        $this->rules['can_bulk_upload'] = 'required|in:' . implode(',', $canBulkUploadEnums);
    }

    /**
     * @param ProjectDTO $project
     * @param $options
     */
    public function additionalChecks(ProjectDTO $project, $options)
    {
        //
    }
}
