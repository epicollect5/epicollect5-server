<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleCategories extends ValidationBase
{
    protected array $rules = [];

    public function __construct()
    {
        $this->rules['category'] = 'required|in:' . implode(',', array_keys(config('epicollect.strings.project_categories')));
    }


    public function additionalChecks()
    {
        //
    }

}
