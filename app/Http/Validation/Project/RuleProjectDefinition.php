<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\Project\RuleProjectExtraDetails as ProjectExtraDetailsValidator;
use ec5\Http\Validation\Project\RuleForm as FormValidator;
use ec5\Http\Validation\Project\RuleInput as InputValidator;

use ec5\Models\Projects\Project;
use ec5\Models\Projects\ProjectDefinition;
use ec5\Models\Projects\ProjectExtra;

use ec5\Libraries\Utilities\Strings;
use Config;
use Illuminate\Support\Str;

class RuleProjectDefinition
{

    protected $projectExtraDetailsValidator;
    protected $formValidator;
    protected $inputValidator;

    public $errors = [];
    protected $stopAtFirstError = false;

    /**
     * @var ProjectExtra
     */
    protected $projectExtra;
    /**
     * @var ProjectDefinition
     */
    protected $projectDefinition;

    protected $mappingCreate;
    protected $limitForms = 0;
    protected $limitInputs = 0;
    protected $limitSearchInputs = 0;
    protected $limitTitles = 0;
    protected $titleCounts = [];

    protected $currentFormRef = ''; //when looping, which form am i currently testing
    protected $counterFormInputs = 0;
    protected $counterSearchInputs = 0;
    protected $hasJumps = [];
    protected $hasLocation = [];
    protected $formNames = []; //test for duplicate for names
    protected $formRefs = []; //holds all form refs

    /**
     * RuleProjectDefinition constructor.
     * @param ProjectExtraDetailsValidator $projectExtraDetailsValidator
     * @param RuleForm $formValidator
     * @param RuleInput $inputValidator
     * @param ProjectExtra $projectExtra
     * @param ProjectDefinition $projectDefinition
     */
    public function __construct(
        ProjectExtraDetailsValidator $projectExtraDetailsValidator,
        FormValidator $formValidator,
        InputValidator $inputValidator,
        ProjectExtra $projectExtra,
        ProjectDefinition $projectDefinition
    ) {

        $this->projectExtra = $projectExtra;
        $this->projectDefinition = $projectDefinition;

        $this->projectExtraDetailsValidator = $projectExtraDetailsValidator;
        $this->formValidator = $formValidator;
        $this->inputValidator = $inputValidator;
        $this->limitForms = Config::get('ec5Limits.formlimits.forms');
        $this->limitInputs = Config::get('ec5Limits.formlimits.inputs');
        $this->limitSearchInputs = Config::get('ec5Limits.search_per_project');
        $this->limitTitles = Config::get('ec5Limits.formlimits.titles');
    }

    /**
     * return the errors array
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * return count of errors array
     *
     * @return boolean
     */
    public function hasErrors()
    {
        return count($this->errors) > 0;
    }

    /**
     * @param array $e
     */
    protected function addArrayToErrors(array $e)
    {
        $this->errors = array_merge($this->errors, $e);
    }

    /**
     * return count of errors array
     *
     */
    public function getProjectExtra()
    {
        return $this->projectExtra;
    }

    /**
     * Validate the Project Definition
     *
     * @param Project $project
     */
    public function validate(Project $project)
    {

        $this->projectExtra = $project->getProjectExtra();
        // Reset the existing data, ready to rebuild
        $this->projectExtra->reset();

        $this->projectDefinition = $project->getProjectDefinition();

        $projectData = $this->projectDefinition->getData()['project'];

        // If failed, just add errors and bail
        if (empty($projectData) || empty($projectData['forms'])) {
            $this->errors['validation'] = ['ec5_67'];
            return;
        }

        $forms = (is_array($projectData['forms'])) ? $projectData['forms'] : [];

        // If no forms
        if (count($forms) == 0) {
            $this->errors['validation'] = ['ec5_66'];
            return;
        }
        // If too many forms
        if (count($forms) > $this->limitForms) {
            $this->errors['validation'] = ['ec5_263'];
            return;
        }

        // Test projectDetails, has to have existing ref as ref , bail if error
        $test = $this->validateProjectDetails($project->ref, $projectData);
        if (!$test) {
            return;
        }

        foreach ($forms as $key => $form) {

            // $data['slug'] = (empty($data['name'])) ? '' : Str::slug($data['name'],"-");
            // Need to keep track of form names or slug to check unique name for each form
            if (empty($form['name']) || in_array($form['name'], $this->formNames)) {
                $this->errors['validation'] = ['ec5_67'];
                if ($this->stopAtFirstError) {
                    break;
                } else {
                    continue;
                }
            } else {
                $form['name'] = trim($form['name']);
                $this->formNames[] = $form['name'];
            }

            // Set our counter to 0
            $this->counterFormInputs = 0;

            // Skip or bail if empty inputs or not array;
            if (empty($form['inputs']) || !is_array($form['inputs'])) {

                $this->errors['validation'] = ['ec5_68'];
                if ($this->stopAtFirstError) {
                    break;
                } else {
                    continue;
                }
            }

            $formInputs = $form['inputs'];
            $this->counterFormInputs = count($formInputs);

            // Skip or bail if no inputs
            if ($this->counterFormInputs == 0) {
                $this->errors['validation'] = ['ec5_68'];
                return;
            }
            // Skip or bail if no inputs
            if ($this->counterFormInputs > $this->limitInputs) {
                $this->errors['validation'] = ['ec5_262'];
                return;
            }

            // Test formDetails, skip and continue to next form
            $test = $this->validateFormDetails($projectData['ref'], $form);
            if (!$test) {
                continue;
            }

            $formRef = $form['ref'];
            $this->currentFormRef = $formRef;
            $this->formRefs[] = $formRef;

            // For each input
            foreach ($formInputs as $inputKey => $input) {

                // Validate top level input break or skip if not valid
                // Note: validateInput also adds the input to the projectStructure inputs[]
                $validInput = $this->validateInput($formRef, $input);

                //todo validate jumps destinations

                if (!$validInput) {
                    if ($this->stopAtFirstError) {
                        break;
                    } else {
                        continue;
                    }
                }

                $inputType = $input['type'];
                $inputRef = $input['ref'];

                // Add  top level link
                $this->projectExtra->addTopLevelRef($formRef, $inputRef);

                switch ($inputType) {

                    case Config::get('ec5Strings.branch'):
                        // Validate branch inputs
                        // Note: inputs are retrieved from 'branch' array in input
                        $test = $this->validateBranchInputs($formRef, $inputRef,
                            $input[Config::get('ec5Strings.branch')]);
                        if (!$test) {
                            if ($this->stopAtFirstError) {
                                break;
                            } else {
                                continue;
                            }
                        }
                        break;
                    case Config::get('ec5Strings.group'):
                        // Validate group inputs
                        // Note: inputs are retrieved from 'group' array in input
                        $test = $this->validateGroupInputs($formRef, $inputRef,
                            $input[Config::get('ec5Strings.group')]);
                        if (!$test) {
                            if ($this->stopAtFirstError) {
                                break;
                            } else {
                                continue;
                            }
                        }
                        break;
                    case Config::get('ec5Strings.inputs_type.searchsingle'):
                    case Config::get('ec5Strings.inputs_type.searchmultiple'):
                        //update search inputs counter
                        $this->counterSearchInputs++;
                }
            }

            if ($this->counterSearchInputs > Config::get('ec5Limits.search_per_project')) {
                $this->errors['validation'] = ['ec5_333'];
                return;
            }
        }

        // Add any extra info collect e.g. "has_location"
        $this->addExtraFormDetails();

    }

    /**
     * validate project structure details
     *
     * @param Existing Project Ref
     * @param $data
     * @return bool
     */
    private function validateProjectDetails($parentRef, $data)
    {

        // Test against project validation rules
        $this->projectExtraDetailsValidator->validate($data, true);

        // If failed, just add errors from validationHandler as well and bail
        if ($this->projectExtraDetailsValidator->hasErrors()) {
            return $this->errorHelper('projectExtraDetailsValidator', 'ec5_63', '',
                $this->projectExtraDetailsValidator->errors());
        }

        // Test against project validation rules
        $this->projectExtraDetailsValidator->additionalChecks($parentRef);

        // If failed, just add errors from validationHandler as well and bail
        if ($this->projectExtraDetailsValidator->hasErrors()) {
            return $this->errorHelper('projectExtraDetailsValidator', 'ec5_63', '',
                $this->projectExtraDetailsValidator->errors());
        }

        // Create and add the project extra details
        $projectExtraDetails = [];
        foreach (Config::get('ec5ProjectStructures.project_extra.project.details') as $property => $value) {
            $projectExtraDetails[$property] = $data[$property] ?? '';
        }

        // Add the project details
        $this->projectExtra->addProjectDetails($projectExtraDetails);

        // Add the entries limits
        $this->projectExtra->addEntriesLimits($data['entries_limits'] ?? []);

        return true;
    }

    /**
     * validate project structure details
     *
     * @param $projectRef
     * @param $form
     * @return bool
     */
    private function validateFormDetails($projectRef, $form)
    {
        // Test against required keys and Form validation rules
        $this->formValidator->validate($form, true);

        // If failed, just add errors from validationHandler as well and bail
        if ($this->formValidator->hasErrors()) {
            return $this->errorHelper('formValidator', 'ec5_64', '', $this->formValidator->errors());
        }

        // Test against Form validation rules
        $this->formValidator->additionalChecks($projectRef, $form);

        // If failed, just add errors from validationHandler as well and bail
        if ($this->formValidator->hasErrors()) {
            return $this->errorHelper('formValidator', 'ec5_64', '', $this->formValidator->errors());
        }

        $form['slug'] = (empty($form['name'])) ? '' : Str::slug($form['name'], "-");
        $this->projectExtra->addFormDetails($form['ref'], $form);

        return true;

    }

    /**
     * Validate an input
     *
     * @param $ownerRef
     * @param $input
     * @param string|null $branchRef
     * @return bool
     */
    private function validateInput($ownerRef, $input, $branchRef = null)
    {

        // Add additional rules to validate (differentiate between a form and a branch)
        $this->inputValidator->addAdditionalRules(!empty($branchRef));

        // Validate input
        $this->inputValidator->validate($input, $check_keys = true);
        $inputRef = (isset($input['ref'])) ? $input['ref'] : '';

        // If failed, just add errors from validationHandler as well and bail
        if ($this->inputValidator->hasErrors()) {
            return $this->errorHelper('inputValidator', 'ec5_65', $inputRef, $this->inputValidator->errors());
        }

        // Test against project validation rules
        $this->inputValidator->additionalChecks($ownerRef);

        // If failed, just add errors from validationHandler as well and bail
        if ($this->inputValidator->hasErrors()) {
            return $this->errorHelper('inputValidator', 'ec5_65', $inputRef, $this->inputValidator->errors());
        }

        // Check number of titles hasn't exceeded the allowed total
        if ($input['is_title']) {
            // If we have a branch ref, use this to check the titles limit
            // i.e. we have a group in a branch, so the group input is added to the branch title count
            if ($this->checkTitlesLimit($branchRef ?? $ownerRef)) {
                return $this->errorHelper('inputValidator', 'ec5_211', $inputRef, $this->inputValidator->errors());
            }
        }

        $type = $input['type'];

        if (isset($input[$type])) {
            $input[$type] = [];
        }
        if ($type == 'location') {
            $this->hasLocation[] = $this->currentFormRef;
        }
        if (count($input['jumps']) > 0) {
            $this->hasJumps[] = $this->currentFormRef;
        }

        $this->projectExtra->addInput($this->currentFormRef, $input['ref'], $input, $branchRef);

        return true;

    }

    /**
     * Validate group inputs
     *
     * @param $formRef
     * @param $inputRef - input ref of the group itself
     * @param $groupInputs
     * @param string|null $branchRef
     * @return bool
     */
    private function validateGroupInputs($formRef, $inputRef, $groupInputs, $branchRef = null)
    {

        $valid = true;

        $hasInputs = (empty($groupInputs)) ? 0 : count($groupInputs);
        $this->counterFormInputs += $hasInputs;

        $test = $this->checkInputLimit($hasInputs);
        if (!$test) {
            return false;
        }

        // Add an array to the project extra for this group
        $this->projectExtra->addFormSpecialType($formRef, Config::get('ec5Strings.group'), $inputRef);

        foreach ($groupInputs as $key => $groupInput) {

            // Test against project validation rules
            $test = $this->validateInput($inputRef, $groupInput, $branchRef);
            if (!$test) {
                $valid = false;
                if ($this->stopAtFirstError) {
                    break;
                } else {
                    continue;
                }
            }
            $this->projectExtra->addFormSpecialTypeInput($formRef, Config::get('ec5Strings.group'), $inputRef,
                $groupInput['ref']);

            //update search inputs counter
            if ($groupInput['type'] === Config::get('ec5Strings.inputs_type.searchsingle')) {
                $this->counterSearchInputs++;
            }
            if ($groupInput['type'] === Config::get('ec5Strings.inputs_type.searchmultiple')) {
                $this->counterSearchInputs++;
            }
        }

        return $valid;
    }

    /**
     * Validate branch inputs
     *
     * @param $formRef
     * @param $inputRef
     * @param $branchInputs
     * @return bool
     */
    private function validateBranchInputs($formRef, $inputRef, $branchInputs)
    {

        $valid = true;

        $hasInputs = (empty($branchInputs)) ? 0 : count($branchInputs);
        $this->counterFormInputs += $hasInputs;

        $test = $this->checkInputLimit($hasInputs);
        if (!$test) {
            return false;
        }

        $this->projectExtra->addFormSpecialType($formRef, Config::get('ec5Strings.branch'), $inputRef);

        foreach ($branchInputs as $key => $branchInput) {

            // Validate the input, passing in the branch ref so we know it's a branch input
            $test = $this->validateInput($inputRef, $branchInput, $inputRef);
            if (!$test) {
                $valid = false;
                if ($this->stopAtFirstError) {
                    break;
                } else {
                    continue;
                }
            }

            $this->projectExtra->addFormSpecialTypeInput($formRef, Config::get('ec5Strings.branch'), $inputRef,
                $branchInput['ref']);

            // Check if this input type is a group
            if ($branchInput['type'] == Config::get('ec5Strings.group')) {
                // Validate group inputs
                // Note: inputs are retrieved from 'group' array in input
                $valid = $this->validateGroupInputs($formRef, $branchInput['ref'],
                    $branchInput[Config::get('ec5Strings.group')], $inputRef);
            }

            //update search inputs counter
            if ($branchInput['type'] === Config::get('ec5Strings.inputs_type.searchsingle')) {
                $this->counterSearchInputs++;
            }
            if ($branchInput['type'] === Config::get('ec5Strings.inputs_type.searchmultiple')) {
                $this->counterSearchInputs++;
            }

        }

        return $valid;

    }

    /**
     * keep an eye on the total number of inputs including sub levels, branches, groups etc
     * @param $hasInputs - check me against 0 or counter is over limit
     * @return bool
     */
    private function checkInputLimit($hasInputs)
    {

        if ($hasInputs == 0) {
            $this->errors['validation'][] = 'ec5_68';
            return false;
        }

        if ($this->counterFormInputs > $this->limitInputs) {
            $this->errors['validation'][] = 'ec5_262';
            return false;
        }

        return true;
    }

    /**
     * Error helper add to errors array
     *
     * @param $type
     * @param $code
     * @param $ref
     * @param $errors
     * @return bool
     */
    private function errorHelper($type, $code, $ref, $errors)
    {
        ($ref == '') ? $this->errors[$type][] = $code : $this->errors[$ref] = [$code];
        $this->addArrayToErrors($errors);
        return false;
    }

    /**
     * @param $ownerRef
     * @return bool
     */
    private function checkTitlesLimit($ownerRef)
    {
        // If we've not seen a count for this form/branch ref yet, set to 0
        if (!isset($this->titleCounts[$ownerRef])) {
            $this->titleCounts[$ownerRef] = 0;
        }
        // Increment count for this form/branch ref title count
        $this->titleCounts[$ownerRef] += 1;

        // If the set limit has been exceeded, error out
        if ($this->titleCounts[$ownerRef] > $this->limitTitles) {
            return true;
        }

        return false;

    }

    /**
     * Add a couple of things to top level forms, ie has location input
     * Data was collected while looping into this->formRefs
     */
    private function addExtraFormDetails()
    {
        foreach ($this->formRefs as $key => $ref) {

            $formDetails = $this->projectExtra->getFormData($ref, 'details');
            if ($formDetails == null) {
                continue;
            }

            $details = [];

            // Check if this form has a location
            $details['has_location'] = in_array($ref, $this->hasLocation);
            $this->projectExtra->addExtraFormDetails($ref, $details);
        }
    }

}
