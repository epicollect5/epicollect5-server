<?php

namespace ec5\Http\Validation\Entries\Search;

use ec5\Http\Validation\ValidationBase;

class RuleAnswersSearch extends ValidationBase
{

    protected $rules = [
        //input_ref is 60 chars, branch_input_ref is 74, nested group input is 88
        'input_ref' => 'required|string|min:60|max:88',
        'form_ref' => 'required|string|min:46|max:46',
        'branch_ref' => 'nullable|string|min:60|max:60',
    ];
}
