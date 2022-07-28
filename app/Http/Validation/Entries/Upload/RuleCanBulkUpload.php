<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\Models\Projects\Project;
use ec5\Http\Validation\ValidationBase;
use Config;

class RuleCanBulkUpload extends ValidationBase
{
    /**F
     * @var array
     */
    protected $rules = [

    ];

    public function __construct()
    {
        $canBulkUploadEnums = Config::get('ec5Enums.can_bulk_upload');
        $this->rules['can_bulk_upload'] = 'required|in:'.implode(',',$canBulkUploadEnums);
    }

    /**
     * @param Project $project
     * @param $options
     */
    public function additionalChecks(Project $project, $options)
    {
        //
    }
}
