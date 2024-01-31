<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Common;

/**
 * Class RuleRadioInput
 * @package ec5\Http\Validation\Entries\Upload\InputRules
 *
 * Radio answers are uniqId() strings, as string and not array
 */
class RuleRadioInput extends RuleInputBase
{
    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param ProjectDTO|null $project
     */
    public function setRules($inputDetails, $answer, ProjectDTO $project)
    {
        // Validate against possible answers
        $possibles = Common::getPossibleAnswers($inputDetails);

        //if the answer_ref does not exist we do trigger an error here, as the UI shows the other options to pick from when editing (it does not with checkbox or search, therefore the different approach here)
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
            $entryStructure->addPossibleAnswer($answer);
        }

        return $answer;
    }
}
