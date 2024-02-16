<?php

declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\View;

use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Services\Entries\EntriesViewService;
use ec5\Services\Mapping\DataMappingService;
use ec5\Traits\Requests\RequestAttributes;
use ec5\Traits\Response\ResponseTools;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

abstract class ViewEntriesControllerBase
{
    use RequestAttributes, ResponseTools;

    protected $dataMappingService;
    protected $entriesViewService;

    public function __construct(
        DataMappingService $dataMappingService,
        EntriesViewService $entriesViewService
    )
    {
        $this->dataMappingService = $dataMappingService;
        $this->entriesViewService = $entriesViewService;
    }

    /**
     * @param array $params
     * @param array $columns
     * @return \Illuminate\Database\Query\Builder
     */
    protected function runQueryHierarchy(array $params, array $columns)
    {
        // NOTE: form_ref is never empty here
        $entryModel = new Entry();
        // Single Entry
        if (!empty($params['uuid'])) {
            $query = $entryModel->getEntry(
                $this->requestedProject()->getId(),
                $params,
                $columns
            );
        } else {
            if (!empty($params['parent_uuid'])) {
                // Child Entries
                $query = $entryModel->getChildEntriesForParent(
                    $this->requestedProject()->getId(),
                    $params,
                    $columns
                );
            } else {
                // All Form Entries
                $query = Entry::getEntriesByForm(
                    $this->requestedProject()->getId(),
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
        $branchEntryModel = new BranchEntry();
        // Single Branch Entry
        if (!empty($params['uuid'])) {
            $query = $branchEntryModel->getEntry(
                $this->requestedProject()->getId(),
                $params,
                $columns
            );
        } else {
            if (!empty($params['branch_owner_uuid'])) {
                // Branch Entries for Branch Ref and Branch Owner
                $query = $branchEntryModel->getBranchEntriesForBranchRefAndOwner(
                    $this->requestedProject()->getId(),
                    $params,
                    $columns
                );
            } else {
                // All Branch Entries for Branch Ref
                $query = $branchEntryModel::getBranchEntriesByBranchRef(
                    $this->requestedProject()->getId(),
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
}
