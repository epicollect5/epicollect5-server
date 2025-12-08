<?php

namespace ec5\Http\Validation\Entries\View;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;

class RuleQueryStringLocations extends ValidationBase
{
    /**
     * @var array
     */
    protected array $rules = [
        'page' => 'nullable|integer',
        'per_page' => 'integer',
        'input_ref' => 'nullable|string',
        'form_ref' => 'nullable|string',
        'branch_ref' => 'nullable|string',
    ];

    /**
     * @param ProjectDTO $project
     * @param $params
     */
    public function additionalChecks(ProjectDTO $project, $params): void
    {
        $projectExtra = $project->getProjectExtra();

        // Check form ref is valid
        if (!empty($params['form_ref']) && !$projectExtra->formExists($params['form_ref'])) {
            $this->errors[$params['form_ref']] = ['ec5_15'];
            return;
        }

        //check input ref is provided, we need to know the location question
        if (empty($params['input_ref'])) {
            $this->errors['rule-query-string'] = ['ec5_243'];
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
