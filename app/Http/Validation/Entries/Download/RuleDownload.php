<?php

namespace ec5\Http\Validation\Entries\Download;

use ec5\Models\Projects\Project;
use ec5\Http\Validation\ValidationBase;


class RuleDownload extends ValidationBase
{
    /**
     * @var array
     */
    protected $rules = [
        'map_index' => 'numeric|min:0',
        'format' => 'required|in:csv,json'
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
