<?php

namespace ec5\Models\Projects;

use Config;

/*
|--------------------------------------------------------------------------
| Project Extra Model
|--------------------------------------------------------------------------
| A model for the JSON Project Extra
|
*/

class ProjectExtra extends ProjectModelBase
{

    /**
     * @param array $data
     */
    public function create(array $data)
    {

        // Retrieve project extra template
        $projectExtraStructure = Config::get('ec5ProjectStructures.project_extra');

        // Replace the {{form_ref}} key with the actual form ref
        $projectExtraStructure['forms'][$data['project']['forms'][0]] = $projectExtraStructure['forms']['{{form_ref}}'];
        unset($projectExtraStructure['forms']['{{form_ref}}']);

        // Replace key values from $data into the $projectExtraStructure
        $this->data = $this->mergeArrays($projectExtraStructure, $data, []);

    }

    /**
     * Reset the data
     */
    public function reset()
    {
        // Retrieve project extra (keys only) template
        $projectExtraStructure = Config::get('ec5ProjectStructures.project_extra_reset');

        $this->data = $projectExtraStructure;
    }

    /* PROJECT */

    /**
     * @param array $data
     */
    public function addProjectDetails(array $data)
    {
        $this->data['project']['details'] = $data;
        $this->data['project']['forms'] = [];

    }

    /**
     * Update project details, if the same keys exist
     * in $this->data and $data
     *
     * @param array $data
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

    /**
     * @param $ref
     * @param $which
     * @return array
     */
    public function getFormData($ref, $which) : array
    {
        return $this->data['forms'][$ref][$which] ?? [];
    }

    /**
     * Add form, form_ref and add details from data
     * data should contains things like name, ref etc
     * update counts
     *
     * @param $formRef
     * @param $data
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
    public function getForms() : array
    {
        return $this->data['forms'] ?? [];
    }

    /**
     * Get ALL FORM REFS otherwise empty array
     *
     * @return array
     **/
    public function getFormRefs() : array
    {
        return $this->data['project']['forms'] ?? [];
    }

    /**
     * Get the form details otherwise empty array
     *
     * @param $formRef
     * @return array
     **/
    public function getFormDetails($formRef) : array
    {
        return $this->data['forms'][$formRef] ?? [];
    }

    /**
     * @param $formRef
     * @param $type
     * @param $inputRef
     */
    public function addFormSpecialType($formRef, $type, $inputRef)
    {
        $this->data['forms'][$formRef][$type][$inputRef] = [];
    }

    /**
     * Check if a form has a parent
     *
     * @param string $formRef
     * @return array|null
     */
    public function formHasParent($formRef)
    {
        for ($i = 0; $i < count($this->data['project']['forms']); $i++) {
            // If we found a match on the first form, we have a top level parent
            if ($i === 0 && $this->data['project']['forms'][$i] === $formRef) {
                return null;
            } else {
                if ($this->data['project']['forms'][$i] === $formRef) {
                    // If we find a match at any other level, return the parent form ref, as we have a child
                    return $this->data['project']['forms'][$i - 1];
                }
            }
        }
        return null;
    }

    /* INPUTS */

    /**
     * @return array
     */
    public function getInputs() : array
    {
        return $this->data['inputs'] ?? [];
    }

    /**
     * @param $formRef
     * @return array
     */
    public function getFormInputs($formRef) : array
    {
        return $this->data['forms'][$formRef]['inputs'] ?? [];
    }

    /**
     * @param string $formRef
     * @param string $branchOwnerInputRef
     * @return array
     */
    public function getBranchInputs($formRef, $branchOwnerInputRef) : array
    {
        return $this->data['forms'][$formRef]['branch'][$branchOwnerInputRef] ?? [];
    }

    /**
     * @param string $formRef
     * @param string $inputRef
     * @return array
     */
    public function getGroupInputs($formRef, $inputRef) : array
    {
        return $this->data['forms'][$formRef]['group'][$inputRef] ?? [];
    }

    /**
     * @param $inputRef
     * @return array
     */
    public function getInputData($inputRef) : array
    {
        return $this->data['inputs'][$inputRef]['data'] ?? [];
    }

    /**
     * @param $inputRef
     * @return array
     */
    public function getInput($inputRef) : array
    {
        return $this->data['inputs'][$inputRef] ?? [];
    }

    /**
     * Return a particular input detail - eg 'type', 'possible_answers' etc
     *
     * @param $inputRef
     * @param $which
     * @return mixed
     */
    public function getInputDetail($inputRef, $which)
    {
        return $this->data['inputs'][$inputRef]['data'][$which] ?? '';
    }

    /**
     * @param $formRef
     * @param $inputRef
     */
    public function addTopLevelRef($formRef, $inputRef)
    {
        if (!isset($this->data['forms'][$formRef])) {
            return;
        }

        $this->data['forms'][$formRef]['inputs'][] = $inputRef;

    }

    /**
     * @param $formRef
     * @param $inputRef
     * @param $input
     * @param null $branchRef
     */
    public function addInput($formRef, $inputRef, $input, $branchRef = null)
    {

        $this->data['inputs'][$inputRef]['data'] = $input;
        $this->dealWithInput($formRef, $inputRef, $input, $branchRef);

    }

    /**
     * Add extra information to the Project Extra for certain inputs
     *
     * @param $formRef
     * @param $inputRef
     * @param $input
     * @param $branchRef
     */
    private function dealWithInput($formRef, $inputRef, $input, $branchRef)
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

    /**
     * @param $formRef
     * @param $type - branch, group
     * @param $ownerRef
     * @param $inputRef
     */
    public function addFormSpecialTypeInput($formRef, $type, $ownerRef, $inputRef)
    {
        $this->data['forms'][$formRef][$type][$ownerRef][] = $inputRef;
    }

    /**
     * Check if a branch exists by ref
     *
     * @param string $formRef
     * @param string $inputRef
     * @return bool
     */
    public function branchExists($formRef, $inputRef)
    {
        return (isset($this->data['forms'][$formRef]['branch'][$inputRef]) ? true : false);
    }

    /**
     * Check if an input belongs to a branch
     *
     * @param string $formRef
     * @param string $inputRef
     * @return bool
     */
    public function isBranchInput($formRef, $inputRef)
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

    /**
     * @param $inputRef
     * @return array
     */
    public function getPossibleAnswers($inputRef)
    {
        $possibleAnswers = $this->getInputDetail($inputRef, 'possible_answers');
        $possibles = [];

        if (!empty($possibleAnswers)) {
            // todo we can get this from the mapping
            foreach ($possibleAnswers as $possibleAnswer) {
                $possibles[] = $possibleAnswer['answer_ref'];
            }
        }

        return $possibles;
    }

    /**
     * @param $inputRef
     * @param $answerRef
     * @return bool
     */
    public function possibleAnswerExists($inputRef, $answerRef) : bool
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
     *
     * @param string $formRef
     * @return array
     */
    public function getBranches($formRef)
    {
        return $this->data['forms'][$formRef]['branch'] ?? [];
    }

    /**
     * Get all form input data, including group inputs but not the main group input
     *
     * @param $formRef
     * @return array
     */
    public function getFormInputData(string $formRef) : array
    {

        $formInputRefs = $this->getFormInputs($formRef);
        $inputs = [];

        foreach ($formInputRefs as $inputRef) {

            $input = $this->getInputData($inputRef);
            // If we have a group, add the group inputs
            if ($input['type'] == Config::get('ec5Strings.inputs_type.group')) {

                $groupInputRefs = $this->getGroupInputs($formRef, $inputRef);

                foreach ($groupInputRefs as $groupInputRef) {

                    $groupInput = $this->getInputData($groupInputRef);
                    $inputs[] = $groupInput;
                }

            } else {
                $inputs[] = $input;
            }

        }

        return $inputs;

    }

    /**
     * Get all branch input data, including group inputs but not the main group input
     *
     * @param string $formRef
     * @param string $branchRef
     * @return array
     */
    public function getBranchInputData(string $formRef, string $branchRef) : array
    {
        $branchInputRefs = $this->getBranchInputs($formRef, $branchRef);
        $inputs = [];

        foreach ($branchInputRefs as $inputRef) {

            $input = $this->getInputData($inputRef);
            // If we have a group, add the group inputs
            if ($input['type'] == Config::get('ec5Strings.inputs_type.group')) {

                $groupInputRefs = $this->getGroupInputs($formRef, $inputRef);

                foreach ($groupInputRefs as $groupInputRef) {

                    $groupInput = $this->getInputData($groupInputRef);
                    $inputs[] = $groupInput;
                }
            } else {
                $inputs[] = $input;
            }
        }

        return $inputs;

    }

    /**
     * @param $ref
     * @return int
     */
    public function getEntriesLimit($ref)
    {
        return $this->data['project']['entries_limits'][$ref] ?? null;
    }

    /**
     *
     */
    public function clearEntriesLimits()
    {
        $this->data['project']['entries_limits'] = [];
    }

    /**
     * @param $ref
     * @param $limitTo
     */
    public function setEntriesLimit($ref, $limitTo)
    {
        $this->data['project']['entries_limits'][$ref] = $limitTo;
    }

    /**
     * @param $entriesLimits
     */
    public function addEntriesLimits($entriesLimits)
    {
        $this->data['project']['entries_limits'] = $entriesLimits;
    }

    /**
     * Add extra form, details, ie, has location, has jumps
     *
     * @param string $formRef
     * @param array
     **/
    public function addExtraFormDetails($formRef, $data)
    {
        foreach ($data as $key => $value) {
            $this->data['forms'][$formRef]['details'][$key] = $value;
        }
    }
}
