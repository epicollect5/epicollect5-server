<?php

namespace ec5\Services;

use ec5\Http\Validation\Entries\View\RuleQueryString;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleAudioInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleBranchInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleCheckboxInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleDateInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleDecimalInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleDropdownInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleGroupInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleIntegerInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleLocationInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RulePhoneInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RulePhotoInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleRadioInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleSearchMultipleInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleSearchSingleInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTextareaInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTextInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTimeInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleVideoInput;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Traits\Requests\RequestAttributes;

class EntriesViewService
{
    use RequestAttributes;

    private $ruleQueryString;
    private $ruleAnswers;
    public $validationErrors;

    public function __construct()
    {
        $this->ruleAnswers = new RuleAnswers(
            new RuleIntegerInput(),
            new RuleDecimalInput(),
            new RuleRadioInput(),
            new RuleTextInput(),
            new RuleTextareaInput(),
            new RuleDateInput(),
            new RuleTimeInput(),
            new RuleLocationInput(),
            new RuleCheckboxInput(),
            new RulePhotoInput(),
            new RuleVideoInput(),
            new RuleAudioInput(),
            new RuleBranchInput(),
            new RuleGroupInput(),
            new RuleDropdownInput(),
            new RulePhoneInput(),
            new RuleSearchSingleInput(),
            new RuleSearchMultipleInput()
        );
        $this->ruleQueryString = new RuleQueryString();
    }

    /**
     * @param $allowedKeys
     * @param $perPage
     * @return array
     */
    public function getSanitizedQueryParams($allowedKeys, $perPage): array
    {
        $params = [];
        //set default keys if missing
        foreach ($allowedKeys as $k) {
            $params[$k] = request()->get($k) ?? '';
        }

        // Defaults for sort by and sort order
        $params['sort_by'] = !empty($params['sort_by']) ? $params['sort_by'] : config('epicollect.mappings.search_data_entries_defaults.sort_by');
        $params['sort_order'] = !empty($params['sort_order']) ? $params['sort_order'] : config('epicollect.mappings.search_data_entries_defaults.sort_order');

        // Set defaults
        if (empty($params['per_page'])) {
            $params['per_page'] = $perPage;
        }
        if (empty($params['page'])) {
            $params['page'] = 1;
        }

        // Check user project role
        // Collectors can only view their own data in private projects
        if (
            $this->requestedProject()->isPrivate()
            && $this->requestedProjectRole()->isCollector()
        ) {
            $params['user_id'] = $this->requestedProjectRole()->getUser()->id;
        }

        // Set default form_ref (first form), if not supplied
        if (empty($params['form_ref'])) {
            $params['form_ref'] = $this->requestedProject()->getProjectDefinition()->getFirstFormRef();
        }

        //if no map_index provided, return default map (check of empty string, as 0 is a valid map index)
        if ($params['map_index'] === '') {
            $params['map_index'] = $this->requestedProject()->getProjectMapping()->getDefaultMapIndex();
        }

        // Format of the data i.e., json, csv
        $params['format'] = !empty($params['format']) ? $params['format'] : config('epicollect.mappings.search_data_entries_defaults.format');
        // Whether to include headers for csv
        $params['headers'] = !empty($params['headers']) ? $params['headers'] : config('epicollect.mappings.search_data_entries_defaults.headers');

        return $params;
    }

    /**
     * @param array $params - Request query param
     * @return bool
     */
    public function areValidQueryParams(array $params): bool
    {
        $this->ruleQueryString->validate($params);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validationErrors = $this->ruleQueryString->errors();
            return false;
        }
        // Do additional checks
        $this->ruleQueryString->additionalChecks($this->requestedProject(), $params);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validationErrors = $this->ruleQueryString->errors();
            return false;
        }

        //todo: not sure we search on answers yet, so we could remove this?
        $inputRef = (empty($params['input_ref'])) ? '' : $params['input_ref'];
        if (empty($inputRef)) {
            return true;
        }
        // Otherwise, check if valid search value i.e., date is date, min max etc.
        $input = $this->requestedProject()->getProjectExtra()->getInputData($inputRef);
        $this->ruleAnswers->validateAnswer($input, $params['search'], $this->requestedProject());
        if ($this->ruleAnswers->hasErrors()) {
            $this->validationErrors = $this->ruleAnswers->errors();
            return false;
        }
        return true;
    }
}