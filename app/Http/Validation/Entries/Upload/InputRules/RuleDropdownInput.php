<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Common;

class RuleDropdownInput extends RuleInputBase
{
    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param ProjectDTO $project
     */
    public function setRules($inputDetails, $answer, ProjectDTO $project)
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
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @return mixed
     */
    public function additionalChecks($inputDetails, $answer, ProjectDTO $project, EntryStructureDTO $entryStructure)
    {
        if (!empty($answer)) {
            // Add possible answer to entry structure
            try {
                $entryStructure->addPossibleAnswer($answer);
            } catch (\Exception $e) {
                \Log::error('Dropdown: possible answer value is invalid', ['answer' => $answer]);
                $this->errors[$inputDetails['ref']] = ['ec5_25'];
            }
        }
        return $answer;
    }
}
