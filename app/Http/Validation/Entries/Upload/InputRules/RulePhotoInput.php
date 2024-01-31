<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\ProjectDTO;

class RulePhotoInput extends RuleInputBase
{

    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param ProjectDTO $project
     */
    public function setRules($inputDetails, $answer, ProjectDTO $project)
    {
        // Override message for regex
        $this->messages['regex'] = 'ec5_81';

        // Set rules based on the input details
        // Source will be the input ref

        // Check photo file name is in correct format
        // NOTE: this must match the mime types allowed in RulePhotoApp and RulePhotoWeb
        $this->rules[$inputDetails['ref']] = ['regex:/^.*\.(jpg|jpeg|png)$/i'];

        // Set remaining rules in the parent class
        parent::setRules($inputDetails, $answer, $project);

    }

}
