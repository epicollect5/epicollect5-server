<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository as EntryStatsRepository;

class RuleEntryLimits extends ValidationBase
{
    protected $rules = [
        'limit' => 'required|integer',
        'limitTo' => 'required|integer|max:50000',
        'formRef' => 'required',
        'branchRef' => 'present'
    ];

    protected $messages = [
        'integer' => 'ec5_27',
        'required' => 'ec5_21',
        'max' => 'ec5_335'
    ];

    /**
     * @var EntryStatsRepository
     */
    protected $entryStatsRepository;

    /**
     * RuleEntryLimits constructor.
     * @param EntryStatsRepository $entryStatsRepository
     */
    public function __construct(EntryStatsRepository $entryStatsRepository)
    {
        $this->entryStatsRepository = $entryStatsRepository;
    }

    /**
     * @param Project $project
     * @param $ref
     * @param $data
     */
    public function additionalChecks(Project $project, $ref, $data)
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
            $maxCount = $this->entryStatsRepository->getMaxCountBranch($project->getId(), $ref);
        } else {
            $maxCount = $this->entryStatsRepository->getMaxCountForm($project->getId(), $ref);
        }

        // Check the entry limit is not < the max count of entries (related to main entry if a branch/child form)
        if ($data['limitTo'] < $maxCount) {
            $this->addAdditionalError($ref, 'ec5_251');
        }

    }

}
