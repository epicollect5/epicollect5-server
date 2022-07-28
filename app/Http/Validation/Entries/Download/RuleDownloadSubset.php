<?php

namespace ec5\Http\Validation\Entries\Download;

use ec5\Models\Projects\Project;
use ec5\Http\Validation\ValidationBase;
use Config;

class RuleDownloadSubset extends ValidationBase
{
    protected $rules = [
        'map_index' => 'numeric|min:0',
        'filename' => 'required'
    ];

    public function __construct()
    {
        $cookieName = Config::get('ec5Strings.cookies.download-entries');
        $this->rules[$cookieName] = 'required';//this is the cookie name
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
