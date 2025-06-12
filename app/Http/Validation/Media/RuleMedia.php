<?php

namespace ec5\Http\Validation\Media;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;

class RuleMedia extends ValidationBase
{
    /**
     * @var array
     */
    protected array $rules = [
        'type' => 'required|in:photo,audio,video',
        'name' => 'required|string'
    ];

    /**
     * RuleMedia constructor.
     */
    public function __construct()
    {
        // Add the format check to rules, from media config file
        $this->rules['format'] = 'required|in:' . implode(',', config('epicollect.media.formats'));
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
