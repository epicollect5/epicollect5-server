<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\Models\Projects\Project;
use ec5\Models\Entries\EntryStructure;
use ec5\Libraries\Utilities\Common;

class RuleDropdownInput extends RuleInputBase
{
    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param Project $project
     */
    public function setRules($inputDetails, $answer, Project $project)
    {
        // Validate against possible answers
        $possibles = Common::getPossibleAnswers($inputDetails);

        $this->rules[$inputDetails['ref']] = ['in:' . implode(',', $possibles)];

        // Set remaining rules in the parent class
        parent::setRules($inputDetails, $answer, $project);
    }

    /**
     * @param $inputDetails
     * @param $answer
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return mixed
     */
    public function additionalChecks($inputDetails, $answer, Project $project, EntryStructure $entryStructure)
    {
        if (!empty($answer)) {
            // Add possible answer to entry structure
            try {
                $entryStructure->addPossibleAnswer($answer);
            }
            catch (\Exception $e) {
                \Log::error('Dropdown: possible answer value is invalid', ['answer' => $answer]);
                $this->errors[$inputDetails['ref']] = ['ec5_25'];
            }
        }
        return $answer;
    }
}
