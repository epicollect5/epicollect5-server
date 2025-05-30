<?php

namespace ec5\Http\Validation\Entries\Download;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;

class RuleDownload extends ValidationBase
{
    /**
     * @var array
     */
    protected array $rules = [
        'map_index' => 'numeric|min:0',
        'format' => 'required|in:csv,json'
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
