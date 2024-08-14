<?php

namespace ec5\Http\Controllers\Api\Entries;

use ec5\DTO\EntryStructureDTO;
use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Entries\Delete\RuleDelete;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Services\Entries\DeleteEntryService;
use ec5\Traits\Eloquent\Remover;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class DeleteController extends Controller
{
    use Remover;

    /*
    |--------------------------------------------------------------------------
    | Delete Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the deletion of entries
    |
    */

    use RequestAttributes;

    protected $errors = [];
    protected $request;

    /**
     * Delete an entry
     *
     * @return JsonResponse
     */
    public function deleteEntry(RuleDelete $ruleDelete, EntryStructureDTO $entryStructure, DeleteEntryService $deleteEntryService)
    {
        // Validate the $data
        $data = request()->get('data');
        $ruleDelete->validate($data);
        if ($ruleDelete->hasErrors()) {
            return Response::apiErrorCode(400, $ruleDelete->errors());
        }

        // Load an entry structure
        $entryStructure->createStructure($data);
        // Add project id to entry structure
        $entryStructure->setProjectId($this->requestedProject()->getId());

        // Perform additional checks on the $entryStructure
        $ruleDelete->additionalChecks($this->requestedProject(), $entryStructure);
        if ($ruleDelete->hasErrors()) {
            return Response::apiErrorCode(400, $ruleDelete->errors());
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
        if ($entry === null) {
            return Response::apiErrorCode(400, ['deletion-entries' => ['ec5_239']]);
        }

        // Check if this user has permission to delete the entry
        if (!$this->requestedProjectRole()->canDeleteEntry($entry)) {
            return Response::apiErrorCode(400, ['deletion-entries' => ['ec5_91']]);
        }

        // Attempt to delete
        if ($isBranch) {
            if (!$deleteEntryService->deleteBranchEntry(
                $this->requestedProject(),
                $entryStructure->getEntryUuid(),
                $entryStructure,
                false)) {
                return Response::apiErrorCode(400, ['deletion-entries-branch' => ['ec5_96']]);
            }
        } else {
            if (!$deleteEntryService->deleteHierarchyEntry($this->requestedProject(), $entryStructure)) {
                return Response::apiErrorCode(400, ['deletion-entries' => ['ec5_96']]);
            }
        }
        return Response::apiSuccessCode('ec5_236');
    }

    /**
     * Delete a chunk of entries
     *
     * @return JsonResponse
     */
    public function deleteEntries()
    {
        // Validate the request
        $data = request()->get('data');
        $projectName = $data['project-name'];

        //no project name passed?
        if (!isset($projectName)) {
            return Response::apiErrorCode(400, ['deletion-entries' => ['ec5_399']]);
        }

        //if we are sending the wrong project name, bail out
        if (trim($this->requestedProject()->name) !== $projectName) {
            return Response::apiErrorCode(400, ['deletion-entries' => ['ec5_399']]);
        }

        //do we have the right permissions?
        if (!$this->requestedProjectRole()->canDeleteEntries()) {
            return Response::apiErrorCode(400, ['errors' => ['ec5_91']]);
        }

        //is the project locked? Otherwise, bail out
        if ($this->requestedProject()->status !== config('epicollect.strings.project_status.locked')) {
            return Response::apiErrorCode(400, ['deletion-entries' => ['ec5_91']]);
        }


        // Attempt to remove a chuck of entries
        try {
            if (!$this->removeEntriesChunk($this->requestedProject()->getId(), $this->requestedProject()->ref)) {
                return Response::apiErrorCode(400, ['errors' => ['ec5_104']]);
            }
            // Success!
            return Response::apiSuccessCode('ec5_400');
        } catch (\Exception $e) {
            \Log::error('Error deletion() entries', ['exception' => $e->getMessage()]);
            return Response::apiErrorCode(400, ['errors' => ['ec5_104']]);
        }
    }
}