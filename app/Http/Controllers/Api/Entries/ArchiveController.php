<?php

namespace ec5\Http\Controllers\Api\Entries;

use ec5\DTO\EntryStructureDTO;
use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Entries\Archive\RuleArchive;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Services\Entries\ArchiveEntryService;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class ArchiveController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Archive Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the archiving of an entry
    |
    */

    use RequestAttributes;

    protected $errors = [];
    protected $request;

    /**
     * Archive an entry
     *
     * @return JsonResponse
     */
    public function index(RuleArchive $ruleArchive, EntryStructureDTO $entryStructure, ArchiveEntryService $archiveEntryService)
    {
        // Validate the $data
        $data = request()->get('data');
        $ruleArchive->validate($data);
        if ($ruleArchive->hasErrors()) {
            return Response::apiErrorCode(400, $ruleArchive->errors());
        }

        // Load an entry structure
        $entryStructure->createStructure($data);
        // Add project id to entry structure
        $entryStructure->setProjectId($this->requestedProject()->getId());

        // Perform additional checks on the $entryStructure
        $ruleArchive->additionalChecks($this->requestedProject(), $entryStructure);
        if ($ruleArchive->hasErrors()) {
            return Response::apiErrorCode(400, $ruleArchive->errors());
        }

        $isBranch = !empty($entryStructure->getOwnerInputRef());

        // Options to be able to retrieve the entry
        $params = ['uuid' => $entryStructure->getEntryUuid(), 'form_ref' => $entryStructure->getFormRef()];

        // Is it a branch entry?
        if ($isBranch) {
            $entryModel = new BranchEntry();
            // Add the owner_entry_uuid to the options, so we know we've been supplied the right params
            $params['owner_entry_uuid'] = $entryStructure->getOwnerUuid();
        } else {
            // Or a main entry?
            $entryModel = new Entry();
            // Add the parent_entry_uuid to the options, so we know we've been supplied the right params
            $params['parent_entry_uuid'] = $entryStructure->getParentUuid();
        }

        // Check this $entryUuid belongs to this project
        // todo check that the entry exists given parent_uuid, branch_owner etc etc
        $entry = $entryModel->getEntry($this->requestedProject()->getId(), $params)->first();
        if (count($entry) == 0) {
            return Response::apiErrorCode(400, ['entry_archive' => ['ec5_239']]);
        }

        // Check if this user has permission to delete the entry
        if (!$this->requestedProjectRole()->canDeleteEntry($entry)) {
            return Response::apiErrorCode(400, ['entry_archive' => ['ec5_91']]);
        }

        // Attempt to Archive
        if ($isBranch) {
            if (!$archiveEntryService->archiveBranchEntry(
                $this->requestedProject(),
                $entryStructure->getEntryUuid(),
                $entryStructure,
                false)) {
                return Response::apiErrorCode(400, ['branch_entry_archive' => ['ec5_96']]);
            }
        } else {
            if (!$archiveEntryService->archiveHierarchyEntry($this->requestedProject(), $entryStructure)) {
                return Response::apiErrorCode(400, ['entry_archive' => ['ec5_96']]);
            }
        }
        return Response::apiSuccessCode('ec5_236');
    }
}