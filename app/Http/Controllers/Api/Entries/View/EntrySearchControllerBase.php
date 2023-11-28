<?php

declare(strict_types=1);

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
    protected $entryRepository;
    protected $branchEntryRepository;
    protected $ruleQueryString;
    protected $ruleAnswers;
    protected $allowedSearchKeys;
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
        Request               $request,
        ApiRequest            $apiRequest,
        ApiResponse           $apiResponse,
        EntryRepository       $entryRepository,
        BranchEntryRepository $branchEntryRepository,
        RuleQueryString       $ruleQueryString,
        RuleAnswers           $ruleAnswers
    )
    {
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
    protected function getRequestParams(Request $request, $perPage)
    {
        $params = [];
        foreach ($this->allowedSearchKeys as $k) {
            $params[$k] = $request->get($k) ?? '';
        }

        // Defaults for sort by and sort order
        $params['sort_by'] = !empty($params['sort_by']) ? $params['sort_by'] : Config::get('ec5Enums.search_data_entries_defaults.sort_by');
        $params['sort_order'] = !empty($params['sort_order']) ? $params['sort_order'] : Config::get('ec5Enums.search_data_entries_defaults.sort_order');

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
            $this->requestedProject->isPrivate()
            && $this->requestedProjectRole->isCollector()
        ) {
            $params['user_id'] = $this->requestedProjectRole->getUser()->id;
        }

        // Set default form_ref (first form), if not supplied
        if (empty($params['form_ref'])) {
            $params['form_ref'] = $this->requestedProject->getProjectDefinition()->getFirstFormRef();
        }

        //if no map_index provide, return default map (check of empty string, as 0 is a valid map index)
        if ($params['map_index'] === '') {
            $params['map_index'] = $this->requestedProject->getProjectMapping()->getDefaultMapIndex();
        }

        // Format of the data i.e., json, csv
        $params['format'] = !empty($params['format']) ? $params['format'] : Config::get('ec5Enums.search_data_entries_defaults.format');
        // Whether to include headers for csv
        $params['headers'] = !empty($params['headers']) ? $params['headers'] : Config::get('ec5Enums.search_data_entries_defaults.headers');

        return $params;
    }

    // Common Validation

    /**
     * @param array $params - Request options
     * @return bool
     */
    protected function validateParams(array $params): bool
    {
        $this->ruleQueryString->validate($params);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validateErrors = $this->ruleQueryString->errors();
            return false;
        }
        // Do additional checks
        $this->ruleQueryString->additionalChecks($this->requestedProject, $params);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validateErrors = $this->ruleQueryString->errors();
            return false;
        }

        $inputRef = (empty($params['input_ref'])) ? '' : $params['input_ref'];

        if (empty($inputRef)) {
            return true;
        }

        // Otherwise, check if valid value i.e., date is date min max etc.
        //$inputType = $this->requestedProject->getProjectExtra()->getInputDetail($inputRef, 'type');
        $value = $params['search'];
        //$value2 = $params['search_two'];

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
    protected function validateValue(string $inputRef, string $value): bool
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
     * @param array $params
     * @param array $columns
     * @return \Illuminate\Database\Query\Builder
     */
    protected function runQuery(array $params, array $columns)
    {
        // NOTE: form_ref is never empty here

        // Single Entry
        if (!empty($params['uuid'])) {
            $query = $this->entryRepository->getEntry(
                $this->requestedProject->getId(),
                $params,
                $columns
            );
        } else {
            if (!empty($params['parent_uuid'])) {
                // Child Entries
                $query = $this->entryRepository->getChildEntriesForParent(
                    $this->requestedProject->getId(),
                    $params,
                    $columns
                );
            }

            // Search based on input ref
            //        else if (!empty($params['input_ref'])) {
            //
            //            // Search based on search value
            //            if (!empty($params['search'])) {
            //                return $this->entryRepository->searchAnswersForInputWithValue(
            //                    $this->requestedProject->getId(),
            //                    $params,
            //                    $columns
            //                );
            //            }
            //
            //        }
            else {
                // All Form Entries
                $query = $this->entryRepository->getEntries(
                    $this->requestedProject->getId(),
                    $params,
                    $columns
                );
            }
        }

        return $query;
    }

    /**
     * @param array $params
     * @param array $columns
     * @return \Illuminate\Database\Query\Builder
     */
    protected function runQueryBranch(array $params, array $columns)
    {

        // NOTE: branch_ref is never empty here

        // Single Branch Entry
        if (!empty($params['uuid'])) {
            $query = $this->branchEntryRepository->getEntry(
                $this->requestedProject->getId(),
                $params,
                $columns
            );
        } else {
            if (!empty($params['branch_owner_uuid'])) {
                // Branch Entries for Branch Ref and Branch Owner
                $query = $this->branchEntryRepository->getBranchEntriesForBranchRefAndOwner(
                    $this->requestedProject->getId(),
                    $params,
                    $columns
                );
            } else {
                // All Branch Entries for Branch Ref
                $query = $this->branchEntryRepository->getBranchEntriesForBranchRef(
                    $this->requestedProject->getId(),
                    $params,
                    $columns
                );
            }
        }

        // todo: do we ever want all branches for a form, regardless or branch ref or owner_uuid?

        return $query;
    }

    /**
     * @param LengthAwarePaginator $entryData
     * @param $params
     */
    protected function appendOptions(LengthAwarePaginator $entryData, $params)
    {
        // Unset the user's user_id, so it's not exposed
        // Note: if this was exposed, it would only be the current user's user_id
        // If the user changed this, it would have no effect
        unset($params['user_id']);
        // Append options to the LengthAwarePaginator
        $entryData->appends($params);
    }

    /**
     * @param LengthAwarePaginator $entryData
     * @return array
     */
    protected function getLinks(LengthAwarePaginator $entryData)
    {
        // Links
        return [
            'self' => $entryData->url($entryData->currentPage()),
            'first' => $entryData->url(1),
            'prev' => $entryData->previousPageUrl(),
            'next' => $entryData->nextPageUrl(),
            'last' => $entryData->url($entryData->lastPage())
        ];
    }

    /**
     * @param LengthAwarePaginator $entryData
     * @param null $newest
     * @param null $oldest
     * @return array
     */
    protected function getMeta(LengthAwarePaginator $entryData, $newest = null, $oldest = null): array
    {
        return [
            'total' => $entryData->total(),
            //imp: cast to int for consistency:
            //imp: sometimes the paginator gives a string back, go figure
            'per_page' => (int)$entryData->perPage(),
            'current_page' => $entryData->currentPage(),
            'last_page' => $entryData->lastPage(),
            // todo - duplication here, remove when dataviewer is rewritten
            'from' => $entryData->currentPage(),
            'to' => $entryData->lastPage(),
            'newest' => $newest,
            'oldest' => $oldest
        ];
    }
}
