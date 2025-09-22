<?php

namespace ec5\Http\Controllers\Api\Entries;

use Cache;
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
use Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

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

    protected array $errors = [];

    /**
     * Delete an entry
     *
     * @param RuleDelete $ruleDelete
     * @param EntryStructureDTO $entryStructure
     * @param DeleteEntryService $deleteEntryService
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public function deleteEntry(RuleDelete $ruleDelete, EntryStructureDTO $entryStructure, DeleteEntryService $deleteEntryService)
    {
        // Validate the $data
        $data = request()->get('data');
        $ruleDelete->validate($data);
        if ($ruleDelete->hasErrors()) {
            return Response::apiErrorCode(400, $ruleDelete->errors());
        }

        //if the project is locked, single entry deletion is not allowed
        if ($this->requestedProject()->status === config('epicollect.strings.project_status.locked')) {
            return Response::apiErrorCode(400, ['deletion-entries' => ['ec5_202']]);
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
                false
            )) {
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
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function validateDeletionRequest()
    {
        // Validate the request
        $data = request()->get('data');
        //no project name passed?
        if (!isset($data['project-name'])) {
            $this->errors[] = ['deletion-entries' => ['ec5_399']];
            return false;
        }

        //if we are sending the wrong project name, bail out
        if (trim($this->requestedProject()->name) !== $data['project-name']) {
            $this->errors[] = ['deletion-entries' => ['ec5_399']];
            return false;
        }

        //do we have the right permissions?
        if (!$this->requestedProjectRole()->canDeleteEntries()) {
            $this->errors[] = ['deletion-entries' => ['ec5_91']];
            return false;
        }

        //is the project locked? Otherwise, bail out
        if ($this->requestedProject()->status !== config('epicollect.strings.project_status.locked')) {
            $this->errors[] = ['deletion-entries' => ['ec5_91']];
            return false;
        }
        return true;
    }

    /**
     * Delete a chunk of entries
     *
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function deleteEntries()
    {
        // Validate the request
        if (!$this->validateDeletionRequest()) {
            return Response::apiErrorCode(400, $this->errors[0]);
        }

        $userId = $this->requestedUser()->id;
        $userCacheKey = 'bulk_entries_deletion_user_' . $userId;
        $lock = Cache::lock($userCacheKey, config('epicollect.setup.locks.duration_bulk_entries_deletion_lock'));

        if ($lock->get()) {
            try {
                // Attempt to remove a chunk of entries
                if (!$this->removeEntriesChunk($this->requestedProject()->getId())) {
                    return Response::apiErrorCode(400, ['errors' => ['ec5_104']]);
                }
                // Success!
                return Response::apiSuccessCode('ec5_400');
            } catch (Throwable $e) {
                Log::error('Error deleting entries', ['exception' => $e->getMessage()]);
                return Response::apiErrorCode(400, ['errors' => ['ec5_104']]);
            } finally {
                // Release the lock
                $lock->release();
            }
        } else {
            return Response::apiErrorCode(400, ['errors' => ['ec5_255']]);
        }
    }


    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function deleteMedia()
    {
        // Validate the request
        if (!$this->validateDeletionRequest()) {
            return Response::apiErrorCode(400, $this->errors[0]);
        }

        $userId = $this->requestedUser()->id;
        $userCacheKey = 'bulk_entries_deletion_user_' . $userId;
        $lock = Cache::lock($userCacheKey, config('epicollect.setup.locks.duration_bulk_entries_deletion_lock'));

        if ($lock->get()) {
            try {
                // Attempt to remove a chunk of media files
                $deleted = $this->removeMediaChunk($this->requestedProject());
                // Success!
                return Response::apiResponse([
                    'code' =>  'ec5_407',
                    'title' => 'Chunk media deleted successfully.',
                    'deleted' => $deleted
                ]);
            } catch (Throwable $e) {
                Log::error('Error deleting media', ['exception' => $e->getMessage()]);
                return Response::apiErrorCode(400, ['errors' => ['ec5_104']]);
            } finally {
                // Release the lock
                $lock->release();
            }
        } else {
            return Response::apiErrorCode(400, ['errors' => ['ec5_255']]);
        }
    }
}
