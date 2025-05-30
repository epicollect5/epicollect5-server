<?php

namespace ec5\Http\Validation\Project;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;
use ec5\Models\Counters\BranchEntryCounter;
use ec5\Models\Counters\EntryCounter;

class RuleEntryLimits extends ValidationBase
{
    protected array $rules = [
        'setLimit' => 'required|in:true,false',//as it comes as a string in the post request
        'limitTo' => 'required|integer|min:0|max:50000',
        'formRef' => 'required',
        'branchRef' => 'present'
    ];

    protected array $messages = [
        'integer' => 'ec5_27',
        'required' => 'ec5_21',
        'max' => 'ec5_335'
    ];


    /**
     * @param ProjectDTO $project
     * @param $ref
     * @param $data
     */
    public function additionalChecks(ProjectDTO $project, $ref, $data): void
    {
        $projectExtra = $project->getProjectExtra();
        $isBranch = false;

        // Check we have a valid form
        if (count($projectExtra->getFormDetails($data['formRef'])) == 0) {
            // Not a valid form
            $this->addAdditionalError($ref, 'ec5_15');
            return;
        }

        // Check we have a valid branch
        if ($data['branchRef']) {
            $isBranch = true;
            if (!$projectExtra->branchExists($data['formRef'], $data['branchRef'])) {
                // Not a valid branch
                $this->addAdditionalError($ref, 'ec5_99');
                return;
            }
        }

        // Get the max counts of entries
        if ($isBranch) {
            $entryCounter = new BranchEntryCounter();
            $maxCount = $entryCounter->getMaxCountBranch($project->getId(), $ref);
        } else {
            $entryCounter = new EntryCounter();
            $maxCount = $entryCounter->getMaxCountForm($project->getId(), $ref);
        }

        // Check the entry limit is not < the max count of entries (related to main entry if a branch/child form)
        if ($data['limitTo'] < $maxCount) {
            $this->addAdditionalError($ref, 'ec5_251');
        }
    }
}
