<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleProjectExtraDetails extends ValidationBase
{
    protected $rules = [
        'name' => 'required|alpha_num_under_spaces|min:3|max:100',
        'slug' => 'not_in:create',//'unique:projects,slug',
        'ref' => 'required',
        'small_description' => 'required|min:3|max:100',
        'access' => 'required|in:public,private',
        'visibility' => 'required|in:listed,hidden',
        'status' => 'required|in:active,trashed,locked',
        'logo_url' => '',
        'description' => '',
        'category' => '',
        'entries_limits' => 'present|array'
    ];

    public function __construct()
    {

    }

    /**
     * Test to make sure the project ref matches so we can edit structure
     *
     * @param $projectRef
     */
    public function additionalChecks($projectRef)
    {
        if ($this->data['ref'] != $projectRef) {
            $this->addAdditionalError($projectRef, 'ec5_321');
        }
    }
}
