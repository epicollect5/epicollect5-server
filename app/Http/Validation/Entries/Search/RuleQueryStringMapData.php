<?php

namespace ec5\Http\Validation\Entries\Search;

use ec5\Models\Projects\Project;
use ec5\Http\Validation\ValidationBase;


class RuleQueryStringMapData extends ValidationBase
{
    /**
     * @var array
     */
    protected $rules = [
        'page' => 'nullable|integer',
        'per_page' => 'integer',
        'input_ref' => 'nullable|string',
        'form_ref' => 'nullable|string',
        'branch_ref' => 'nullable|string',
    ];

    /**
     * @param Project $project
     * @param $options
     */
    public function additionalChecks(Project $project, $options)
    {

        $projectExtra = $project->getProjectExtra();

        // Check form ref is valid
        if (!empty($options['form_ref']) && count($projectExtra->getFormDetails($options['form_ref'])) === 0) {
            $this->errors[$options['form_ref']] = ['ec5_15'];
            return;
        }

        // Check input ref exists
        if (!empty($options['input_ref']) && count($projectExtra->getInput($options['input_ref'])) === 0) {
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
