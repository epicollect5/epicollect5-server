<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\Http\Validation\ValidationBase;

class RuleUniqueness extends ValidationBase
{
    protected array $rules = [
        'type' => 'required|in:entry,branch_entry',
        'id' => 'required|min:36|max:36',

        'entry.entry_uuid' => 'required_if:type,entry|same:id|min:36|max:36',
        'entry.input_ref' => 'required_if:type,entry',
        'entry.answer' => 'required_if:type,entry',

        'branch_entry.entry_uuid' => 'required_if:type,branch_entry|same:id|min:36|max:36',
        'branch_entry.input_ref' => 'required_if:type,branch_entry',
        'branch_entry.answer' => 'required_if:type,branch_entry',

        'attributes' => 'required|array',
        'attributes.form.ref' => 'required|min:46|max:46',
        'attributes.form.type' => 'required|in:hierarchy',

        'relationships' => 'required|array',
        'relationships.parent' => 'present|array',
        'relationships.parent.data' => 'array',
        'relationships.parent.data.parent_form_ref' => 'min:46|max:46',
        'relationships.parent.data.parent_entry_uuid' => 'min:36|max:36',

        'relationships.branch' => 'present|array',
        'relationships.branch.data' => 'array',
        'relationships.branch.data.owner_input_ref' => 'min:60|max:60',
        'relationships.branch.data.owner_entry_uuid' => 'min:36|max:36'
    ];

    protected array $messages = [
        'in' => 'ec5_29',
        'required' => 'ec5_20',
        'array' => 'ec5_87',
        'date' => 'ec5_79',
        'same' => 'ec5_53',
        'required_unless' => 'ec5_20',
        'required_if' => 'ec5_21',
        'min' => 'ec5_28',
        'max' => 'ec5_28',
        'present' => 'ec5_20',
        'boolean' => 'ec5_29'
    ];


}
