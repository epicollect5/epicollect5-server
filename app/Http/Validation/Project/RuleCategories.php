<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Config;

class RuleCategories extends ValidationBase
{
    protected $rules = [];

    public function __construct()
    {
        $this->rules['category'] = 'required|in:' . implode(',', Config::get('ec5Enums.project_categories'));
    }


    public function additionalChecks()
    {
        //
    }

}
