<?php
declare(strict_types = 1);

namespace ec5\Http\Controllers\Api\Entries\View;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Api\ProjectApiControllerBase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

use ec5\Http\Validation\Entries\Search\RuleQueryString;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;

use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository;
use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository;

use Config;


abstract class EntrySearchControllerBase extends ProjectApiControllerBase
{

    /**
     * @var
     */
    protected $entryRepository;

    /**
     * @var
     */
    protected $branchEntryRepository;

    /**
     * @var
     */
    protected $ruleQueryString;

    /**
     * @var
     */
    protected $ruleAnswers;

    /**
     * @var
     */
    protected $allowedSearchKeys;

    /**
     * @var
     */
    protected $validateErrors;

    /**
     * EntrySearchControllerBase constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param EntryRepository $entryRepository
     * @param BranchEntryRepository $branchEntryRepository
     * @param RuleQueryString $ruleQueryString
     * @param RuleAnswers $ruleAnswers
     */
    public function __construct(
        Request $request,
        ApiRequest $apiRequest,
        ApiResponse $apiResponse,
        EntryRepository $entryRepository,
        BranchEntryRepository $branchEntryRepository,
        RuleQueryString $ruleQueryString,
        RuleAnswers $ruleAnswers
    ) {

        parent::__construct($request, $apiRequest, $apiResponse);

        $this->entryRepository = $entryRepository;
        $this->branchEntryRepository = $branchEntryRepository;
        $this->ruleAnswers = $ruleAnswers;
        $this->ruleQueryString = $ruleQueryString;

    }

    /**
     * @param Request $request
     * @param $perPage
     * @return array
     */
    protected function getRequestOptions(Request $request, $perPage)
    {
        $options = [];
        foreach ($this->allowedSearchKeys as $k) {
            $options[$k] = $request->get($k) ?? '';
        }

        // Defaults for sort by and sort order
        $options['sort_by'] = !empty($options['sort_by']) ? $options['sort_by'] : Config::get('ec5Enums.search_data_entries_defaults.sort_by');
        $options['sort_order'] = !empty($options['sort_order']) ? $options['sort_order'] : Config::get('ec5Enums.search_data_entries_defaults.sort_order');

        // Set defaults
        if (empty($options['per_page'])) {
            $options['per_page'] = $perPage;
        }
        if (empty($options['page'])) {
            $options['page'] = 1;
        }

        // Check user project role
        // Collectors can only view their own data in private projects
        if ($this->requestedProject->isPrivate()
            && $this->requestedProjectRole->isCollector()
        ) {
            $options['user_id'] = $this->requestedProjectRole->getUser()->id;
        }


        // Set default form_ref (first form), if not supplied
        if (empty($options['form_ref'])) {
            $options['form_ref'] = $this->requestedProject->getProjectDefinition()->getFirstFormRef();
        }

        //if no map_index provide, return default map (check of empty string, as 0 is a valid map index)
        if ($options['map_index'] === '') {
            $options['map_index'] = $this->requestedProject->getProjectMapping()->getDefaultMapIndex();
        }

        // Format of the data i.e. json, csv
        $options['format'] = !empty($options['format']) ? $options['format'] : Config::get('ec5Enums.search_data_entries_defaults.format');
        // Whether to include headers for csv
        $options['headers'] = !empty($options['headers']) ? $options['headers'] : Config::get('ec5Enums.search_data_entries_defaults.headers');

        return $options;

    }

    // Common Validation

    /**
     * @param array $options - Request options
     * @return bool
     */
    protected function validateOptions(array $options) : bool
    {

        $this->ruleQueryString->validate($options);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validateErrors = $this->ruleQueryString->errors();
            return false;
        }
        // Do additional checks
        $this->ruleQueryString->additionalChecks($this->requestedProject, $options);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validateErrors = $this->ruleQueryString->errors();
            return false;
        }

        $inputRef = (empty($options['input_ref'])) ? '' : $options['input_ref'];

        if (empty($inputRef)) {
            return true;
        }

        // Otherwise check if valid value ie date is date min max etc
        //$inputType = $this->requestedProject->getProjectExtra()->getInputDetail($inputRef, 'type');
        $value = $options['search'];
        //$value2 = $options['search_two'];

        return $this->validateValue($inputRef, $value);

    }

    /**
     * Validate the value of an input
     * Using the RuleAnswers validation class
     *
     * @param string $inputRef
     * @param string $value
     * @return bool
     */
    protected function validateValue(string $inputRef, string $value) : bool
    {

        $input = $this->requestedProject->getProjectExtra()->getInputData($inputRef);
        $this->ruleAnswers->validateAnswer($input, $value, $this->requestedProject);
        if ($this->ruleAnswers->hasErrors()) {
            $this->validateErrors = $this->ruleAnswers->errors();
            return false;
        }
        return true;
    }

    //END Common Validation

    /**
     * @param array $options
     * @param array $columns
     * @return \Illuminate\Database\Query\Builder
     */
    protected function runQuery(array $options, array $columns)
    {
        // NOTE: form_ref is never empty here

        // Single Entry
        if (!empty($options['uuid'])) {
            $query = $this->entryRepository->getEntry(
                $this->requestedProject->getId(),
                $options,
                $columns
            );
        } else {
            if (!empty($options['parent_uuid'])) {
                // Child Entries
                $query = $this->entryRepository->getChildEntriesForParent(
                    $this->requestedProject->getId(),
                    $options,
                    $columns
                );
            }

            // Search based on input ref
//        else if (!empty($options['input_ref'])) {
//
//            // Search based on search value
//            if (!empty($options['search'])) {
//                return $this->entryRepository->searchAnswersForInputWithValue(
//                    $this->requestedProject->getId(),
//                    $options,
//                    $columns
//                );
//            }
//
//        }
            else {
                // All Form Entries
                $query = $this->entryRepository->getEntries(
                    $this->requestedProject->getId(),
                    $options,
                    $columns
                );
            }
        }

        return $query;
    }

    /**
     * @param array $options
     * @param array $columns
     * @return \Illuminate\Database\Query\Builder
     */
    protected function runQueryBranch(array $options, array $columns)
    {

        // NOTE: branch_ref is never empty here

        // Single Branch Entry
        if (!empty($options['uuid'])) {
            $query = $this->branchEntryRepository->getEntry(
                $this->requestedProject->getId(),
                $options,
                $columns
            );
        } else {
            if (!empty($options['branch_owner_uuid'])) {
                // Branch Entries for Branch Ref and Branch Owner
                $query = $this->branchEntryRepository->getBranchEntriesForBranchRefAndOwner(
                    $this->requestedProject->getId(),
                    $options,
                    $columns
                );
            }

            // Search based on input ref
//        else if (!empty($options['input_ref'])) {
//
//            // Search based on search value
//            if (!empty($options['search'])) {
//                return $this->branchEntryRepository->searchAnswersForInputWithValue(
//                    $this->requestedProject->getId(),
//                    $options,
//                    $columns
//                );
//            }
//
//        }

            else {
                // All Branch Entries for Branch Ref
                $query = $this->branchEntryRepository->getBranchEntriesForBranchRef(
                    $this->requestedProject->getId(),
                    $options,
                    $columns
                );
            }
        }

        // todo: do we ever want all branches for a form, regardless or branch ref or owner_uuid?

        return $query;

    }

    /**
     * @param LengthAwarePaginator $entryData
     * @param $options
     */
    protected function appendOptions(LengthAwarePaginator $entryData, $options)
    {
        // Unset the user's user_id so it's not exposed
        // Note: if this was exposed, it would only be the current user's user_id
        // If the user changed this it would have no effect
        unset($options['user_id']);
        // Append options to the LengthAwarePaginator
        $entryData->appends($options);
    }

    /**
     * @param LengthAwarePaginator $entryData
     * @return array
     */
    protected function getLinks(LengthAwarePaginator $entryData)
    {
        // Links
        $links = [
            'self' => $entryData->url($entryData->currentPage()),
            'first' => $entryData->url(1),
            'prev' => $entryData->previousPageUrl(),
            'next' => $entryData->nextPageUrl(),
            'last' => $entryData->url($entryData->lastPage())
        ];

        return $links;
    }

    /**
     * @param LengthAwarePaginator $entryData
     * @param null $newest
     * @param null $oldest
     * @return array
     */
    protected function getMeta(LengthAwarePaginator $entryData, $newest = null, $oldest = null) : array
    {
        $meta = [
            'total' => $entryData->total(),
            'per_page' => $entryData->perPage(),
            'current_page' => $entryData->currentPage(),
            'last_page' => $entryData->lastPage(),
            // todo - duplication here, remove when dataviewer is rewritten
            'from' => $entryData->currentPage(),
            'to' => $entryData->lastPage(),
            'newest' => $newest,
            'oldest' => $oldest
        ];

        return $meta;
    }
}
