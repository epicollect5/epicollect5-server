<?php

namespace ec5\Http\Validation\Entries\Search;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;


class RuleQueryString extends ValidationBase
{
    /**
     * @var array
     */
    protected $rules = [
        'page' => 'integer',
        'per_page' => 'integer',
        'form_ref' => 'nullable|string',
        'uuid' => 'nullable|min:36',
        'parent_form_ref' => 'nullable|string',
        'parent_uuid' => 'nullable|min:36',
        'branch_ref' => 'nullable|string',
        'branch_owner_uuid' => 'nullable|string',
        'input_ref' => 'nullable|string',
        'search' => 'nullable',
        'search_two' => 'nullable',
        'search_op' => 'nullable|in:equals,like,between,less,greater',
        'map_index' => 'numeric|min:0',
        // Sort by dates/titles
        'sort_by' => 'nullable|string|in:created_at,uploaded_at,title',
        'sort_order' => 'nullable|string|in:asc,desc,ASC,DESC',
        // Filter by dates
        'filter_by' => 'string|in:created_at,uploaded_at',
        'filter_from' => 'nullable|date',
        'filter_to' => 'nullable|date',
        // Format and headers (headers for csv)
        'format' => 'nullable|in:csv,json',
        'headers' => 'nullable|in:true,false',
        'title' => 'nullable|string',
    ];

    /**
     * @param ProjectDTO $project
     * @param $options
     */
    public function additionalChecks(ProjectDTO $project, $options)
    {

        $projectExtra = $project->getProjectExtra();

        // Check form ref is valid
        if (!empty($options['form_ref']) && count($projectExtra->getFormDetails($options['form_ref'])) === 0) {
            $this->errors[$options['form_ref']] = ['ec5_15'];
            return;
        }

        // Check parent form ref is valid
        if (!empty($options['parent_form_ref']) && count($projectExtra->getFormDetails($options['parent_form_ref'])) === 0) {
            $this->errors[$options['parent_form_ref']] = ['ec5_15'];
            return;
        }

        // Check input ref exists
        if (!empty($options['input_ref']) && count($project->$projectExtra($options['input_ref'])) === 0) {
            $this->errors[$options['input_ref']] = ['ec5_84'];
            return;
        }

        // Check branch exists
        if (!empty($options['branch_ref']) && !$projectExtra->branchExists($options['form_ref'], $options['branch_ref'])) {
            $this->errors[$options['branch_ref']] = ['ec5_99'];
            return;
        }
    }

}
