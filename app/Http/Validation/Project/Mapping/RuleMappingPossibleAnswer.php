<?php

namespace ec5\Http\Validation\Project\Mapping;

use ec5\Http\Validation\ValidationBase;

class RuleMappingPossibleAnswer extends ValidationBase
{
    /**
     * @var array
     */
    protected array $rules = [
        'map_to' => 'required|string|max:150|regex:/((?![<>]).)$/',
    ];

}
