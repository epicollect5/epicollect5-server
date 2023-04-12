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
use Log;

abstract class EntrySearchControllerBase extends ProjectApiControllerBase
{
    protected $entryRepository;
    protected $branchEntryRepository;
    protected $ruleQueryString;
    protected $ruleAnswers;
    protected $ruleAnswersSearch;
    protected $allowedSearchParams;
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

    //this function filter the search params, removing the ones not allowed
    //and adding some defaults to make request queries work 
    //even when some params are missing, by providing a default response
    protected function prepareSearchParams(Request $request, $perPage)
    {
        $searchParams = [];
        foreach ($this->allowedSearchParams as $k) {
            $searchParams[$k] = $request->get($k) ?? '';
        }

        // Defaults for sort by and sort order
        $searchParams['sort_by'] = !empty($searchParams['sort_by']) ? $searchParams['sort_by'] : Config::get('ec5Enums.search_data_entries_defaults.sort_by');
        $searchParams['sort_order'] = !empty($searchParams['sort_order']) ? $searchParams['sort_order'] : Config::get('ec5Enums.search_data_entries_defaults.sort_order');

        // Set defaults
        if (empty($searchParams['per_page'])) {
            $searchParams['per_page'] = $perPage;
        }
        if (empty($searchParams['page'])) {
            $searchParams['page'] = 1;
        }

        // Check user project role
        // Collectors can only view their own data in private projects
        if (
            $this->requestedProject->isPrivate()
            && $this->requestedProjectRole->isCollector()
        ) {
            $searchParams['user_id'] = $this->requestedProjectRole->getUser()->id;
        }

        // Set default form_ref (first form), if not supplied
        if (empty($searchParams['form_ref'])) {
            $searchParams['form_ref'] = $this->requestedProject->getProjectDefinition()->getFirstFormRef();
        }

        //if no map_index provide, return default map (check of empty string, as 0 is a valid map index)
        if ($searchParams['map_index'] === '') {
            $searchParams['map_index'] = $this->requestedProject->getProjectMapping()->getDefaultMapIndex();
        }

        // Format of the data i.e. json, csv
        $searchParams['format'] = !empty($searchParams['format']) ? $searchParams['format'] : Config::get('ec5Enums.search_data_entries_defaults.format');
        // Whether to include headers for csv
        $searchParams['headers'] = !empty($searchParams['headers']) ? $searchParams['headers'] : Config::get('ec5Enums.search_data_entries_defaults.headers');

        return $searchParams;
    }

    // Common Validation

    /**
     * @param array $searchParams - Request options
     * @return bool
     */
    protected function validateSearchParams(array $searchParams): bool
    {
        $this->ruleQueryString->validate($searchParams);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validateErrors = $this->ruleQueryString->errors();
            return false;
        }
        // Do additional checks
        $this->ruleQueryString->additionalChecks($this->requestedProject, $searchParams);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validateErrors = $this->ruleQueryString->errors();
            return false;
        }

        $inputRef = (empty($searchParams['input_ref'])) ? '' : $searchParams['input_ref'];

        if (empty($inputRef)) {
            return true;
        }

        // Otherwise check if valid value ie date is date min max etc
        //$inputType = $this->requestedProject->getProjectExtra()->getInputDetail($inputRef, 'type');
        $value = $searchParams['search'];
        //$value2 = $searchParams['search_two'];

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
     * @param array $searchParams
     * @param array $columns
     * @return \Illuminate\Database\Query\Builder
     */
    protected function runQuery(array $searchParams, array $columns)
    {
        // NOTE: form_ref is never empty here

        // Single Entry
        if (!empty($searchParams['uuid'])) {
            $query = $this->entryRepository->getEntry(
                $this->requestedProject->getId(),
                $searchParams,
                $columns
            );
        } else {
            if (!empty($searchParams['parent_uuid'])) {
                // Child Entries
                $query = $this->entryRepository->getChildEntriesForParent(
                    $this->requestedProject->getId(),
                    $searchParams,
                    $columns
                );
            }

            // Search based on input ref
            //        else if (!empty($searchParams['input_ref'])) {
            //
            //            // Search based on search value
            //            if (!empty($searchParams['search'])) {
            //                return $this->entryRepository->searchAnswers(
            //                    $this->requestedProject->getId(),
            //                    $searchParams,
            //                    $columns
            //                );
            //            }
            //
            //        }
            else {
                // All Form Entries
                $query = $this->entryRepository->getEntries(
                    $this->requestedProject->getId(),
                    $searchParams,
                    $columns
                );
            }
        }

        return $query;
    }

    /**
     * @param array $searchParams
     * @param array $columns
     * @return \Illuminate\Database\Query\Builder
     */
    protected function runQueryBranch(array $searchParams, array $columns)
    {

        // NOTE: branch_ref is never empty here

        // Single Branch Entry
        if (!empty($searchParams['uuid'])) {
            $query = $this->branchEntryRepository->getEntry(
                $this->requestedProject->getId(),
                $searchParams,
                $columns
            );
        } else {
            if (!empty($searchParams['branch_owner_uuid'])) {
                // Branch Entries for Branch Ref and Branch Owner
                $query = $this->branchEntryRepository->getBranchEntriesForBranchRefAndOwner(
                    $this->requestedProject->getId(),
                    $searchParams,
                    $columns
                );
            }

            // Search based on input ref
            //        else if (!empty($searchParams['input_ref'])) {
            //
            //            // Search based on search value
            //            if (!empty($searchParams['search'])) {
            //                return $this->branchEntryRepository->searchAnswers(
            //                    $this->requestedProject->getId(),
            //                    $searchParams,
            //                    $columns
            //                );
            //            }
            //
            //        }

            else {
                // All Branch Entries for Branch Ref
                $query = $this->branchEntryRepository->getBranchEntriesForBranchRef(
                    $this->requestedProject->getId(),
                    $searchParams,
                    $columns
                );
            }
        }

        // todo: do we ever want all branches for a form, regardless or branch ref or owner_uuid?

        return $query;
    }

    /**
     * @param LengthAwarePaginator $entryData
     * @param $searchParams
     */
    protected function appendOptions(LengthAwarePaginator $entryData, $searchParams)
    {
        // Unset the user's user_id so it's not exposed
        // Note: if this was exposed, it would only be the current user's user_id
        // If the user changed this it would have no effect
        unset($searchParams['user_id']);
        // Append options to the LengthAwarePaginator
        $entryData->appends($searchParams);
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
    protected function getMeta(LengthAwarePaginator $entryData, $newest = null, $oldest = null): array
    {
        $meta = [
            'total' => $entryData->total(),
            //imp: cast to int for consistency:
            //imp: sometimes the paginator gives a string back, go figure
            'per_page' => (int) $entryData->perPage(),
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
