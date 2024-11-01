<?php

namespace ec5\Http\Validation\Entries\Download;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;

class RuleDownloadSubset extends ValidationBase
{
    protected $rules = [
        'map_index' => 'numeric|min:0',
        'filename' => 'required'
    ];

    public function __construct()
    {
        $cookieName = config('epicollect.setup.cookies.download_entries');
        $this->rules[$cookieName] = 'required';//this is the cookie name
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
