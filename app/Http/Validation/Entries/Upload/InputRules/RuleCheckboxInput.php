<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Common;
use Illuminate\Support\Str;

class RuleCheckboxInput extends RuleInputBase
{
    public function setRules(array $inputDetails, array|string|null $answer, ProjectDTO $project): void
    {
        // Set rules based on the input details
        // Source will be the input ref
        $this->rules[$inputDetails['ref']] = ['array'];

    }

    public function additionalChecks(array $inputDetails, array|string|null $answer, ProjectDTO $project, EntryStructureDTO $entryStructure): array|string|null
    {
        if (empty($answer)) {
            // Always default empty checkbox answer to []
            $answer = [];
        }

        if (!is_array($answer)) {
            $this->errors[$inputDetails['ref']] = ['ec5_25'];
            return false;
        }


        $possibles = Common::getPossibleAnswers($inputDetails);

        /**
         * The below check is commented out to cover the case when a possible answer
         * is removed but old entries have that possible answer selected.
         * Since the UI is not showing it anymore, the user cannot fix it
         * as the user cannot un-check the pre-selected checkbox as it does not appear
         * on the UI anymore.
         * Anyway, that check is useless, if there is a  wrong answer_ref ,
         * the answer does not appear. All good apparently, but double check.
         */

        // If the answer contains anything not in the structure possible answers, error
        //            if (count(array_diff($answer, $possibles)) > 0) {
        //                $this->errors[$inputDetails['ref']] = ['ec5_25'];
        //            }

        //uploading duplicate refs? (bulk upload, the apps do not allow them)
        $hasDuplicates = count($answer) > count(array_unique($answer));
        if ($hasDuplicates) {
            $this->errors[$inputDetails['ref']] = ['ec5_25'];
        }

        //uploading invalid values? (bulk upload, the apps do not allow them)
        //For bulk uploads, wrong values are wrapped with '-' to trigger an error
        //since we are uploading answer_ref(s), this works
        foreach ($answer as $value) {
            if (Str::contains($value, '-')) {
                $this->errors[$inputDetails['ref']] = ['ec5_29'];
            }
        }

        if (!$this->hasErrors()) {
            // Loop each given answer
            foreach ($answer as $answerRef) {

                //any null answer refs? Coming from bulk upload
                if ($answerRef === null) {
                    $this->errors[$inputDetails['ref']] = ['ec5_25'];
                    break;
                }

                // If we have a match, add to 'answer' array
                if (in_array($answerRef, $possibles)) {
                    // Add possible answer to entry structure
                    $entryStructure->addPossibleAnswer($answerRef);
                }
            }
        }

        return $answer;
    }
}
