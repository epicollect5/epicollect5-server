<?php

namespace ec5\Http\Validation\Entries\View;

use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\Http\Validation\ValidationBase;


class RuleQueryString extends ValidationBase
{
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
     * @param $params
     */
    public function additionalChecks(ProjectDTO $project, $params)
    {
        $projectExtra = $project->getProjectExtra();

        // Check form ref is valid
        if (!empty($params['form_ref']) && !$projectExtra->formExists($params['form_ref'])) {
            $this->errors[$params['form_ref']] = ['ec5_15'];
            return;
        }

        // Check parent form ref is valid
        if (!empty($params['parent_form_ref']) && !$projectExtra->formExists($params['parent_form_ref'])) {
            $this->errors[$params['parent_form_ref']] = ['ec5_15'];
            return;
        }

        // Check input ref exists
        if (!empty($params['input_ref']) && !$projectExtra->inputExists($params['input_ref'])) {
            $this->errors[$params['input_ref']] = ['ec5_84'];
            return;
        }

        // Check branch exists
        if (!empty($params['branch_ref']) && !$projectExtra->branchExists($params['form_ref'], $params['branch_ref'])) {
            $this->errors[$params['branch_ref']] = ['ec5_99'];
        }

    }
}
