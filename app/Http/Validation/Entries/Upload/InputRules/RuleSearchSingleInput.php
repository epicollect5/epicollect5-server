<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\Models\Entries\EntryStructure;
use ec5\Models\Projects\Project;
use ec5\Libraries\Utilities\Common;
use Illuminate\Support\Str;

class RuleSearchSingleInput extends RuleInputBase
{

    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param Project $project
     */
    public function setRules($inputDetails, $answer, Project $project)
    {
        // Set rules based on the input details
        // Source will be the input ref
        $this->rules[$inputDetails['ref']] = ['array'];

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
        if (!empty($answer) && count($answer) > 0) {

            if (!is_array($answer)) {
                $this->errors[$inputDetails['ref']] = ['ec5_25'];
                return $answer;
            }

            //check that search single has got only 1 single answer
            if (count($answer) > 1) {
                $this->errors[$inputDetails['ref']] = ['ec5_397'];
            }

            $possibles = Common::getPossibleAnswers($inputDetails);

            /**
             * The below check is commented out to cover the case when a possible answer
             * is removed but old entries have that possible answer selected.
             * Since the UI is not showing it anymore, the user cannot fix it.
             * Anyway, that check is useless, if there is a  wrong answer_ref ,
             * the answer does not appear. All good apparently, but double check.
             */

            // If the answer contains anything not in the structure possible answers, error
//            if (count(array_diff($answer, $possibles)) > 0) {
//                $this->errors[$inputDetails['ref']] = ['ec5_25'];
//            }
            //uploading invalid values? (bulk upload, the apps do not allow them)
            //For bulk uploads, wrong values are wrapped with '-' to trigger an error
            foreach ($answer as $key => $value) {
                if (Str::contains($value, '-')) {
                    $this->errors[$inputDetails['ref']] = ['ec5_29'];
                }
            }

            if (!$this->hasErrors()) {
                // Loop each given answer
                foreach ($answer as $answerRef) {

                    //any null anwer refs? Coming from bulk upload
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
        } else {
            // Always default empty checkbox answer to []
            $answer = [];
        }

        return $answer;
    }
}
