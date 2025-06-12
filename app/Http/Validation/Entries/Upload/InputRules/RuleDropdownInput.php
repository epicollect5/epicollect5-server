<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Common;
use Log;
use Throwable;

class RuleDropdownInput extends RuleInputBase
{
    public function setRules(array $inputDetails, array|string|null $answer, ProjectDTO $project): void
    {
        // Validate against possible answers
        $possibles = Common::getPossibleAnswers($inputDetails);

        $this->rules[$inputDetails['ref']] = ['in:' . implode(',', $possibles)];

        // Set remaining rules in the parent class
        parent::setRules($inputDetails, $answer, $project);
    }

    public function additionalChecks(array $inputDetails, string|array|null $answer, ProjectDTO $project, EntryStructureDTO $entryStructure): array|string|null
    {
        if (!empty($answer)) {

            if (!is_string($answer)) {
                $this->errors[$inputDetails['ref']] = ['ec5_25'];
                return $answer;
            }

            // Add possible answer to entry structure
            try {
                $entryStructure->addPossibleAnswer($answer);
            } catch (Throwable $e) {
                Log::error('Dropdown: possible answer value is invalid', [
                    'exception' => $e->getMessage(),
                    'answer' => $answer
                ]);
                $this->errors[$inputDetails['ref']] = ['ec5_25'];
            }
        }
        return $answer;
    }
}
