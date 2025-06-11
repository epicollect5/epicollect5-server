<?php

namespace ec5\Http\Validation\Project;

use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\Http\Validation\Project\RuleProjectExtraDetails as ProjectExtraDetailsValidator;
use Illuminate\Support\Str;

class RuleProjectDefinition
{
    protected RuleProjectExtraDetails $projectExtraDetailsValidator;
    protected RuleForm $ruleForm;
    protected RuleInput $ruleInput;
    public array $errors = [];
    protected ProjectExtraDTO $projectExtra;
    protected ProjectDefinitionDTO $projectDefinition;
    protected array $counterTitles = [];
    protected string $currentFormRef = ''; //when looping, which form
    protected int $counterFormInputs;
    protected int $counterSearchInputs;
    protected array $hasJumps = [];
    protected array $hasLocation = [];
    protected array $formNames = []; //test for duplicate for names
    protected array $formRefs = []; //holds all form refs

    public function __construct(
        ProjectExtraDetailsValidator $projectExtraDetailsValidator,
        RuleForm                     $ruleForm,
        RuleInput                    $ruleInput,
        ProjectExtraDTO              $projectExtra,
        ProjectDefinitionDTO         $projectDefinition
    ) {

        $this->projectExtra = $projectExtra;
        $this->projectDefinition = $projectDefinition;
        $this->projectExtraDetailsValidator = $projectExtraDetailsValidator;
        $this->ruleForm = $ruleForm;
        $this->ruleInput = $ruleInput;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    protected function addArrayToErrors(array $e): void
    {
        $this->errors = array_merge($this->errors, $e);
    }

    public function getProjectExtra(): ProjectExtraDTO
    {
        return $this->projectExtra;
    }

    /**
     * Validate the Project Definition
     */
    public function validate(ProjectDTO $project): bool
    {
        $this->projectExtra = $project->getProjectExtra();
        // Reset the existing data, ready to rebuild
        $this->projectExtra->reset();
        $this->projectDefinition = $project->getProjectDefinition();
        $projectData = $this->projectDefinition->getData()['project'];
        // If failed, just add errors and bail
        if (empty($projectData) || empty($projectData['forms'])) {
            $this->errors['validation'] = ['ec5_67'];
            return false;
        }

        $forms = (is_array($projectData['forms'])) ? $projectData['forms'] : [];

        // If no forms
        if (count($forms) == 0) {
            $this->errors['validation'] = ['ec5_66'];
            return false;
        }
        // If too many forms
        if (count($forms) > config('epicollect.limits.formsMaxCount')) {
            $this->errors['validation'] = ['ec5_263'];
            return false;
        }

        // Test projectDetails, has to have existing ref as ref, bail if error
        $test = $this->validateProjectDetails($project->ref, $projectData);
        if (!$test) {
            return false;
        }

        $this->formNames = [];
        $this->formRefs = [];
        //search inputs currently 5 max per project
        $this->counterSearchInputs = 0;
        $this->counterTitles = [];
        foreach ($forms as $form) {
            //cannot have a form name empty
            if (empty($form['name'])) {
                $this->errors['validation'] = ['ec5_246'];
                break;
            }

            // Need to keep track of form names or slug to check unique name for each form
            if (in_array($form['name'], $this->formNames)) {
                $this->errors['validation'] = ['ec5_245'];
                break;
            } else {
                $form['name'] = trim($form['name']);
                $this->formNames[] = $form['name'];
            }

            // Need to keep track of ref to check uniqueness for each form ref
            if (in_array($form['ref'], $this->formRefs)) {
                $this->errors['validation'] = ['ec5_224'];
                break;
            } else {
                $this->formRefs[] = $form['ref'];
            }

            // Set our counter to 0
            $this->counterFormInputs = 0;
            // Skip or bail if empty inputs or not array;
            if (empty($form['inputs']) || !is_array($form['inputs'])) {
                $this->errors['validation'] = ['ec5_68'];
                break;
            }

            $formInputs = $form['inputs'];
            $this->counterFormInputs = count($formInputs);

            // Skip or bail if no inputs
            if ($this->counterFormInputs == 0) {
                $this->errors['validation'] = ['ec5_68'];
                return false;
            }
            // Skip or bail if no inputs
            if ($this->counterFormInputs > config('epicollect.limits.inputsMaxCount')) {
                $this->errors['validation'] = ['ec5_262'];
                return false;
            }

            // Test form metadata
            if (!$this->isFormValid($projectData['ref'], $form)) {
                return false;
            }

            $formRef = $form['ref'];
            $this->currentFormRef = $formRef;
            $this->formRefs[] = $formRef;


            foreach ($formInputs as $input) {
                // Validate top level input break or skip if not valid
                // Note: validateInput also adds the input to the projectStructure inputs[]
                if (!$this->isInputValid($formRef, $input)) {
                    break;
                }
                //todo validate jumps destinations

                $inputType = $input['type'];
                $inputRef = $input['ref'];

                $this->projectExtra->addTopLevelRef($formRef, $inputRef);

                switch ($inputType) {
                    case config('epicollect.strings.branch'):
                        // Note: inputs are retrieved from 'branch' array in input
                        if (!$this->areValidBranchInputs(
                            $formRef,
                            $inputRef,
                            $input[config('epicollect.strings.branch')]
                        )
                        ) {
                            break;
                        }
                        break;
                    case config('epicollect.strings.group'):
                        // Note: inputs are retrieved from 'group' array in input
                        if (!$this->areValidGroupInputs(
                            $formRef,
                            $inputRef,
                            $input[config('epicollect.strings.group')]
                        )
                        ) {
                            break;
                        }
                        break;
                    case config('epicollect.strings.inputs_type.searchsingle'):
                    case config('epicollect.strings.inputs_type.searchmultiple'):
                        //update search inputs counter
                        $this->counterSearchInputs++;
                }
            }

            //if too many search inputs for this project, return error
            if ($this->counterSearchInputs > config('epicollect.limits.searchMaxCount')) {
                $this->errors['validation'] = ['ec5_333'];
                return false;
            }
        }

        // Add any extra info collect e.g. "has_location"
        $this->addExtraFormDetails();

        return true;
    }

    /**
     * validate project structure details
     */
    private function validateProjectDetails($parentRef, $data): bool
    {
        // Test against project validation rules
        $this->projectExtraDetailsValidator->validate($data, true);
        // If failed, just add errors from validationHandler as well and bail
        if ($this->projectExtraDetailsValidator->hasErrors()) {
            return $this->errorHelper(
                'projectExtraDetailsValidator',
                'ec5_63',
                '',
                $this->projectExtraDetailsValidator->errors()
            );
        }
        // Test against project validation rules
        $this->projectExtraDetailsValidator->additionalChecks($parentRef);
        // If failed, just add errors from validationHandler as well and bail
        if ($this->projectExtraDetailsValidator->hasErrors()) {
            return $this->errorHelper(
                'projectExtraDetailsValidator',
                'ec5_63',
                '',
                $this->projectExtraDetailsValidator->errors()
            );
        }
        // Create and add the project extra details
        $projectExtraDetails = [];
        foreach (config('epicollect.structures.project_extra.project.details') as $property => $value) {
            $projectExtraDetails[$property] = $data[$property] ?? '';
        }
        // Add the project details
        $this->projectExtra->addProjectDetails($projectExtraDetails);
        // Add the entries limits
        $this->projectDefinition->addEntriesLimits($data['entries_limits'] ?? []);
        return true;
    }

    private function isFormValid($projectRef, $form): bool
    {
        // Test against required keys and Form validation rules
        $this->ruleForm->validate($form, true);
        // If failed, just add errors from validationHandler as well and bail
        if ($this->ruleForm->hasErrors()) {
            $errorCodeFirst = array_values($this->ruleForm->errors())[0][0];
            $this->errors['validation'][] = $errorCodeFirst;
            return false;
        }

        // Test against Form validation rules
        $this->ruleForm->additionalChecks($projectRef, $form);

        // If failed, just add errors from validationHandler as well and bail
        if ($this->ruleForm->hasErrors()) {
            $errorCodeFirst = array_values($this->ruleForm->errors())[0][0];
            $this->errors['validation'][] = $errorCodeFirst;
            return false;
        }

        $form['slug'] = (empty($form['name'])) ? '' : Str::slug($form['name']);
        $this->projectExtra->addFormDetails($form['ref'], $form);

        return true;
    }

    /**
     * Validate an input
     */
    private function isInputValid($ownerRef, $input, $branchRef = ''): bool
    {
        // Add additional rules to validate (differentiate between a form and a branch)
        $this->ruleInput->addAdditionalRules(!empty($branchRef));
        // Validate input
        $this->ruleInput->validate($input, true);
        $inputRef = (isset($input['ref'])) ? $input['ref'] : '';
        // If failed, just add errors from validationHandler as well and bail
        if ($this->ruleInput->hasErrors()) {
            return $this->errorHelper('ruleInput', 'ec5_65', $inputRef, $this->ruleInput->errors());
        }
        // Test against project validation rules
        $this->ruleInput->additionalChecks($ownerRef);

        // If failed, just add errors from validationHandler as well and bail
        if ($this->ruleInput->hasErrors()) {
            return $this->errorHelper('ruleInput', 'ec5_65', $inputRef, $this->ruleInput->errors());
        }
        // Check number of titles hasn't exceeded the allowed total
        if ($input['is_title']) {
            // If we have a branch ref (not empty string), use this to check the title max count
            // i.e., we have a group in a branch, so the group input is added to the branch title count
            if ($this->isValidTitlesCount($branchRef ?: $ownerRef)) {
                return $this->errorHelper('ruleInput', 'ec5_211', $inputRef, $this->ruleInput->errors());
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
    private function areValidGroupInputs($formRef, $inputRef, $groupInputs, ?string $branchRef = ''): bool
    {
        $valid = true;
        $hasInputs = (empty($groupInputs)) ? 0 : count($groupInputs);
        $this->counterFormInputs += $hasInputs;

        $test = $this->isValidInputsCount($hasInputs);
        if (!$test) {
            return false;
        }

        // Add an array to the project extra for this group
        $this->projectExtra->addGroup($formRef, $inputRef);

        foreach ($groupInputs as $groupInput) {

            // Test against project validation rules
            if (!$this->isInputValid(
                $inputRef,
                $groupInput,
                $branchRef
            )
            ) {
                $valid = false;
                break;
            }
            $this->projectExtra->addGroupInput(
                $formRef,
                $inputRef,
                $groupInput['ref']
            );

            //update search inputs counter
            if ($groupInput['type'] === config('epicollect.strings.inputs_type.searchsingle')) {
                $this->counterSearchInputs++;
            }
            if ($groupInput['type'] === config('epicollect.strings.inputs_type.searchmultiple')) {
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
    private function areValidBranchInputs($formRef, $inputRef, $branchInputs): bool
    {
        $valid = true;
        $hasInputs = (empty($branchInputs)) ? 0 : count($branchInputs);
        $this->counterFormInputs += $hasInputs;

        $test = $this->isValidInputsCount($hasInputs);
        if (!$test) {
            return false;
        }

        $this->projectExtra->addBranch($formRef, $inputRef);

        foreach ($branchInputs as $branchInput) {
            // Validate the input, passing in the branch ref, so we know it's a branch input
            if (!$this->isInputValid($inputRef, $branchInput, $inputRef)) {
                $valid = false;
                break;
            }

            $this->projectExtra->addBranchInput(
                $formRef,
                $inputRef,
                $branchInput['ref']
            );

            // Check if this input type is a group
            if ($branchInput['type'] == config('epicollect.strings.group')) {
                // Validate group inputs
                // Note: inputs are retrieved from 'group' array in input
                if (!$this->areValidGroupInputs(
                    $formRef,
                    $branchInput['ref'],
                    $branchInput[config('epicollect.strings.group')],
                    $inputRef
                )) {
                    $valid = false;
                    break;
                }
            }

            //update search inputs counter
            if ($branchInput['type'] === config('epicollect.strings.inputs_type.searchsingle')) {
                $this->counterSearchInputs++;
            }
            if ($branchInput['type'] === config('epicollect.strings.inputs_type.searchmultiple')) {
                $this->counterSearchInputs++;
            }

        }
        return $valid;
    }

    /**
     * keep an eye on the total number of inputs including sub-levels, branches, groups etc
     */
    private function isValidInputsCount($hasInputs): bool
    {
        if ($hasInputs == 0) {
            $this->errors['validation'][] = 'ec5_68';
            return false;
        }

        if ($this->counterFormInputs > config('epicollect.limits.inputsMaxCount')) {
            $this->errors['validation'][] = 'ec5_262';
            return false;
        }
        return true;
    }

    /**
     * Error helper add to errors array
     */
    private function errorHelper($type, $code, $ref, $errors): bool
    {
        ($ref == '') ? $this->errors[$type][] = $code : $this->errors[$ref] = [$code];
        $this->addArrayToErrors($errors);
        return false;
    }

    private function isValidTitlesCount($ownerRef): bool
    {
        // If we've not seen a count for this form/branch ref yet, set to 0
        if (!isset($this->counterTitles[$ownerRef])) {
            $this->counterTitles[$ownerRef] = 0;
        }
        // Increment count for this form/branch ref title count
        $this->counterTitles[$ownerRef] += 1;
        // If the set limit has been exceeded, error out
        if ($this->counterTitles[$ownerRef] > config('epicollect.limits.titlesMaxCount')) {
            return true;
        }
        return false;
    }

    /**
     * Add a couple of things to top level forms, i.e., has location input
     * Data was collected while looping into this->formRefs
     */
    private function addExtraFormDetails(): void
    {
        foreach ($this->formRefs as $ref) {
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
