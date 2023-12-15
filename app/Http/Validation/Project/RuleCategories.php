<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Config;

class RuleCategories extends ValidationBase
{
    protected $rules = [];

    public function __construct()
    {
        $this->rules['category'] = 'required|in:' . implode(',', array_keys(config('epicollect.strings.project_categories')));
    }


    public function additionalChecks()
    {
        //
    }

}
