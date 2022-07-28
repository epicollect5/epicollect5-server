<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Illuminate\Validation\Rule;
use Config;

class RuleSearch extends ValidationBase
{
    protected $rules = [];

    public function __construct()
    {
        $this->rules['name'] = 'alpha_num_under_spaces|max:50';
        $this->rules['sort_by'] = Rule::in(['name', 'created_at', 'total_entries']);
        $this->rules['sort_order'] = Rule::in(['asc', 'desc']);
        $this->rules['page'] = 'integer|min:1';
    }

    public function additionalChecks()
    {
        //
    }
}
