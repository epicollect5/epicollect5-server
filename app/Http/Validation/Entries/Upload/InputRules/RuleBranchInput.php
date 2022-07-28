<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;


use ec5\Models\Projects\Project;

class RuleBranchInput extends RuleInputBase
{

    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param Project $project
     */
    public function setRules($inputDetails, $answer, Project $project)
    {
        // No rules to set for branches
    }

}
