<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleForm extends ValidationBase
{
    protected array $rules = [
        'ref' => 'required',
        'type' => 'required|in:hierarchy',
        'slug' => 'required',
        'inputs' => 'array'
    ];

    /**
     * RuleInput constructor.
     */
    public function __construct()
    {
        $this->rules['name'] = 'required|max:' . config('epicollect.limits.form_name_maxlength');
    }

    /**
     * Check that the form ref begins with the project ref
     * Validate the form
     *
     * @param $projectRef
     * @param $form
     */
    public function additionalChecks($projectRef, $form)
    {
        $this->isValidRef($projectRef);

        if ($this->hasErrors()) {
            return;
        }

        //form name only allows alphanumeric chars and space, '-', '_'
        if (!preg_match("/^[a-zA-Z0-9_\- ]+$/", $form['name'])) {
            $this->errors[$form['ref']] = ['ec5_29'];
        }

        //form slug only allows alphanumeric chars and '-'
        if (!preg_match("/^[a-zA-Z0-9\-]+$/", $form['slug'])) {
            $this->errors[$form['ref']] = ['ec5_29'];
        }

        if ($form['name']) {
            if ($this->hasErrors()) {
                return;
            }
        }

        $inputs = $form['inputs'];

        // Validate the jumps in this form
        $this->validateJumps($inputs);

    }

    /**
     * @param array $inputs
     */
    public function validateJumps(array $inputs)
    {

        $inputRefs = [];

        // Loop all inputs and create map of input refs
        foreach ($inputs as $position => $input) {
            // Store a reverse map of input ref => input position
            $inputRefs[$input['ref']] = $position;
        }

        // Now loop and check each jump
        foreach ($inputs as $position => $input) {

            switch ($input['type']) {
                case 'branch':
                    $branchInputs = $input['branch'];
                    // Validate the branch input jumps
                    $this->validateJumps($branchInputs);
                    break;
                case 'group':
                    $groupInputs = $input['group'];
                    // Group jumps are not allowed
                    foreach ($groupInputs as $groupPosition => $groupInput) {
                        if (count($groupInput['jumps']) > 0) {
                            $this->addAdditionalError($groupInput['ref'], 'ec5_320');
                            return;
                        }
                    }
                    break;
            }

            // Validate main input jumps
            // If this input has jumps
            if (count($input['jumps']) > 0) {

                foreach ($input['jumps'] as $jump => $jumpDetails) {

                    // END of form is ok
                    if ($jumpDetails['to'] == 'END') {
                        continue;
                    }

                    // Valid input ref is ok
                    if (array_key_exists($jumpDetails['to'], $inputRefs)) {
                        // Check the position of the jump is greater than this input's position
                        // And equal to or greater than the input after next i.e. position + 2
                        // this means the "jump.to" destination is valid
                        if ($inputRefs[$jumpDetails['to']] >= ($position + 2)) {
                            continue;
                        }
                    }
                    // Otherwise error
                    $this->addAdditionalError($input['ref'], 'ec5_264');
                    return;
                }
            }
        }
    }
}
