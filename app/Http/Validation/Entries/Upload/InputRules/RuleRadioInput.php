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
    public function setRules(array $inputDetails, string|array|null $answer, ProjectDTO $project): void
    {
        // Validate against possible answers
        $possibles = Common::getPossibleAnswers($inputDetails);

        //if the answer_ref does not exist we do trigger an error here, as the UI shows the other options to pick from when editing (it does not with checkbox or search, therefore the different approach here)
        $this->rules[$inputDetails['ref']] = ['in:' . implode(',', $possibles)];

        // Set remaining rules in the parent class
        parent::setRules($inputDetails, $answer, $project);

    }

    public function additionalChecks(array $inputDetails, string|array|null $answer, ProjectDTO $project, EntryStructureDTO $entryStructure): array|string|null
    {
        if (!empty($answer)) {

            if(!is_string($answer)) {
                $this->errors[$inputDetails['ref']] = ['ec5_25'];
                return $answer;
            }
            // Add possible answer to entry structure
            $entryStructure->addPossibleAnswer($answer);
        }

        return $answer;
    }
}
