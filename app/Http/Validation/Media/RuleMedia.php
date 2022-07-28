<?php

namespace ec5\Http\Validation\Media;

use ec5\Models\Projects\Project;
use ec5\Http\Validation\ValidationBase;
use Config;

class RuleMedia extends ValidationBase
{
    /**
     * @var array
     */
    protected $rules = [
        'type' => 'required|in:photo,audio,video',
        'name' => 'required|string'
    ];

    /**
     * RuleMedia constructor.
     */
    public function __construct()
    {
        // Add the format check to rules, from media config file
        $this->rules['format'] = 'required|in:' . implode(Config::get('ec5Media.viewable'), ',');
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
