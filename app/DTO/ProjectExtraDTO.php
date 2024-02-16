<?php

namespace ec5\DTO;

use ec5\Libraries\Utilities\Arrays;

/*
|--------------------------------------------------------------------------
| Project Extra DTO
|--------------------------------------------------------------------------
| A DTO for the JSON Project Extra
|
*/

class ProjectExtraDTO extends ProjectDTOBase
{
    public function create(array $data)
    {
        // Retrieve project extra template
        $projectExtraStructure = config('epicollect.structures.project_extra');
        // Replace the {{form_ref}} key with the actual form ref
        $projectExtraStructure['forms'][$data['project']['forms'][0]] = $projectExtraStructure['forms']['{{form_ref}}'];
        unset($projectExtraStructure['forms']['{{form_ref}}']);
        // Replace key values from $data into the $projectExtraStructure
        $this->data = Arrays::merge($projectExtraStructure, $data);
    }

    public function reset()
    {
        // Retrieve project extra (keys only) template
        $projectExtraStructure = config('epicollect.structures.project_extra_reset');
        $this->data = $projectExtraStructure;
    }

    /* PROJECT */
    public function addProjectDetails(array $data)
    {
        $this->data['project']['details'] = $data;
        $this->data['project']['forms'] = [];
    }

    /**
     * Update project details, if the same keys exist
     * in $this->data and $data
     */
    public function updateProjectDetails(array $data)
    {
        foreach ($data as $key => $value) {
            if (isset($this->data['project']['details'][$key])) {
                $this->data['project']['details'][$key] = $value;
            }
        }
    }

    /* FORMS */
    public function getFormData($ref, $which): array
    {
        return $this->data['forms'][$ref][$which] ?? [];
    }

    /**
     * Add form, form_ref and add details from data
     * data should contain things like name, ref etc
     * update counts
     */
    public function addFormDetails($formRef, $data)
    {
        $this->data['project']['forms'][] = $formRef;
        $this->data['forms'][$formRef]['details'] = $data;
        $this->data['forms'][$formRef]['inputs'] = [];
        $this->data['forms'][$formRef]['lists'] = [
            'location_inputs' => [],
            'multiple_choice_inputs' => [
                'form' => [
                    'order' => []
                ],
                'branch' => []
            ]
        ];
        $this->data['forms'][$formRef]['branch'] = [];
        $this->data['forms'][$formRef]['group'] = [];
    }

    /**
     * Get ALL FORMS otherwise empty array
     *
     * @return array
     **/
    public function getForms(): array
    {
        return $this->data['forms'] ?? [];
    }

    /**
     * Get the form details otherwise empty array
     **/
    public function getFormDetails($formRef): array
    {
        return $this->data['forms'][$formRef] ?? [];
    }

    public function addBranch($formRef, $inputRef)
    {
        $type = config('epicollect.strings.inputs_type.branch');
        $this->data['forms'][$formRef][$type][$inputRef] = [];
    }

    public function addGroup($formRef, $inputRef)
    {
        $type = config('epicollect.strings.inputs_type.group');
        $this->data['forms'][$formRef][$type][$inputRef] = [];
    }

    /* INPUTS */
    public function getInputs(): array
    {
        return $this->data['inputs'] ?? [];
    }

    public function getFormInputs($formRef): array
    {
        return $this->data['forms'][$formRef]['inputs'] ?? [];
    }

    public function getBranchInputs($formRef, $branchOwnerInputRef): array
    {
        return $this->data['forms'][$formRef]['branch'][$branchOwnerInputRef] ?? [];
    }

    public function getGroupInputs($formRef, $inputRef): array
    {
        return $this->data['forms'][$formRef]['group'][$inputRef] ?? [];
    }

    public function getInputData($inputRef): array
    {
        return $this->data['inputs'][$inputRef]['data'] ?? [];
    }

    public function getInput($inputRef): array
    {
        return $this->data['inputs'][$inputRef] ?? [];
    }

    public function inputExists($inputRef): bool
    {
        return isset($this->data['inputs'][$inputRef]);
    }

    /**
     * Return a particular input detail - eg 'type', 'possible_answers' etc
     */
    public function getInputDetail($inputRef, $which)
    {
        return $this->data['inputs'][$inputRef]['data'][$which] ?? '';
    }

    public function addTopLevelRef($formRef, $inputRef)
    {
        if (!isset($this->data['forms'][$formRef])) {
            return;
        }
        $this->data['forms'][$formRef]['inputs'][] = $inputRef;
    }

    public function addInput($formRef, $inputRef, $input, $branchRef = null)
    {
        $this->data['inputs'][$inputRef]['data'] = $input;
        $this->addInputExtraLists($formRef, $inputRef, $input, $branchRef);
    }

    /**
     * Add extra info to the Project Extra for certain questions
     *
     * Lists of multiple choice and location questions
     * to be used by dataviewer
     */
    private function addInputExtraLists($formRef, $inputRef, $input, $branchRef)
    {
        switch ($input['type']) {
            //the following types have all possible answers
            case 'dropdown':
            case 'radio':
            case 'checkbox':
            case 'searchsingle':
            case 'searchmultiple':
                $possibleAnswers = [];
                foreach ($input['possible_answers'] as $possibleAnswer) {
                    $possibleAnswers[$possibleAnswer['answer_ref']] = $possibleAnswer['answer'];
                }
                // Branch or Form?
                if ($branchRef) {
                    // Add to the order
                    $this->data['forms'][$formRef]['lists']['multiple_choice_inputs']['branch'][$branchRef]['order'][] = $inputRef;
                    $this->data['forms'][$formRef]['lists']['multiple_choice_inputs']['branch'][$branchRef][$inputRef] =
                        [
                            'question' => $input['question'],
                            'possible_answers' => $possibleAnswers
                        ];

                } else {
                    // Add to the order
                    $this->data['forms'][$formRef]['lists']['multiple_choice_inputs']['form']['order'][] = $inputRef;
                    $this->data['forms'][$formRef]['lists']['multiple_choice_inputs']['form'][$inputRef] =
                        [
                            'question' => $input['question'],
                            'possible_answers' => $possibleAnswers
                        ];
                }
                break;
            case 'location':
                $this->data['forms'][$formRef]['lists']['location_inputs'][] = [
                    'input_ref' => $inputRef,
                    'branch_ref' => $branchRef,
                    'question' => $input['question']
                ];
                break;
        }
    }

    public function addBranchInput($formRef, $ownerRef, $inputRef)
    {
        $type = config('epicollect.strings.inputs_type.branch');
        $this->data['forms'][$formRef][$type][$ownerRef][] = $inputRef;
    }

    public function addGroupInput($formRef, $ownerRef, $inputRef)
    {
        $type = config('epicollect.strings.inputs_type.group');
        $this->data['forms'][$formRef][$type][$ownerRef][] = $inputRef;
    }

    /**
     * Check if a form exists by ref
     */
    public function formExists($formRef): bool
    {
        return isset($this->data['forms'][$formRef]);
    }

    /**
     * Check if a branch exists by ref
     */
    public function branchExists($formRef, $branchRef): bool
    {
        return isset($this->data['forms'][$formRef]['branch'][$branchRef]);
    }

    /**
     * Check if an input belongs to a branch
     */
    public function isBranchInput($formRef, $inputRef): bool
    {
        if (isset($this->data['forms'][$formRef]['branch'])) {

            // Loop each branch
            foreach ($this->data['forms'][$formRef]['branch'] as $branchRef => $branchInputRefs) {

                if (is_array($branchInputRefs)) {
                    // Then loop each of the branch's inputs
                    foreach ($branchInputRefs as $branchInputRef) {
                        // If we have a match, then this input belongs in a branch
                        if ($inputRef == $branchInputRef) {
                            return true;
                        } else if (isset($this->data['forms'][$formRef]['group'][$branchInputRef])) {
                            // Check if the input is inside a group in a branch

                            // Loop each group
                            foreach ($this->data['forms'][$formRef]['group'][$branchInputRef] as $groupInputRef) {

                                // If we have a match, then this input belongs in a group in a branch
                                if ($inputRef == $groupInputRef) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    public function possibleAnswerExists($inputRef, $answerRef): bool
    {
        $possibleAnswers = $this->getInputDetail($inputRef, 'possible_answers');

        foreach ($possibleAnswers as $possibleAnswer) {
            if ($answerRef == $possibleAnswer['answer_ref']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get branches for a form
     */
    public function getBranches($formRef)
    {
        return $this->data['forms'][$formRef]['branch'] ?? [];
    }

    /**
     * Add extra form, details, ie, has location, has jumps
     *
     **/
    public function addExtraFormDetails($formRef, $data)
    {
        foreach ($data as $key => $value) {
            $this->data['forms'][$formRef]['details'][$key] = $value;
        }
    }
}
