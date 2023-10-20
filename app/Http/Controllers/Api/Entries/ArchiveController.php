<?php

namespace ec5\Http\Controllers\Api\Entries;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Api\ProjectApiControllerBase;

use ec5\Http\Validation\Entries\Archive\RuleArchive as ArchiveValidator;

use ec5\Libraries\EC5Logger\EC5Logger;
use ec5\Models\Entries\EntryStructure;
use ec5\Repositories\QueryBuilder\Entry\Archive\EntryRepository as EntryArchive;
use ec5\Repositories\QueryBuilder\Entry\Archive\BranchEntryRepository as BranchEntryArchive;
use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository as EntrySearch;
use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository as BranchEntrySearch;
use ec5\Repositories\QueryBuilder\Stats\Entry\EntryRepository as EntryStats;
use ec5\Repositories\QueryBuilder\Stats\Entry\BranchEntryRepository as BranchEntryStats;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository;

use Illuminate\Http\Request;

class ArchiveController extends ProjectApiControllerBase
{
    /*
    |--------------------------------------------------------------------------
    | Archive Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the archiving of an entry
    |
    */

    /**
     * @var EntryArchive
     */
    protected $entryArchive;

    /**
     * @var BranchEntryArchive
     */
    protected $branchEntryArchive;

    /**
     * @var EntrySearch
     */
    protected $entrySearch;

    /**
     * @var BranchEntrySearch
     */
    protected $branchEntrySearch;

    /**
     * @var EntryStats
     */
    protected $entryStats;

    /**
     * @var BranchEntryStats
     */
    protected $branchEntryStats;

    /**
     * ArchiveController constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param EntryArchive $entryArchive
     * @param BranchEntryArchive $branchEntryArchive
     * @param EntrySearch $entrySearch
     * @param BranchEntrySearch $branchEntrySearch
     * @param EntryStats $entryStats
     * @param BranchEntryStats $branchEntryStats
     */
    public function __construct(Request            $request,
                                ApiRequest         $apiRequest,
                                ApiResponse        $apiResponse,
                                EntryArchive       $entryArchive,
                                BranchEntryArchive $branchEntryArchive,
                                EntrySearch        $entrySearch,
                                BranchEntrySearch  $branchEntrySearch,
                                EntryStats         $entryStats,
                                BranchEntryStats   $branchEntryStats
    )
    {

        $this->entryArchive = $entryArchive;
        $this->branchEntryArchive = $branchEntryArchive;
        $this->entrySearch = $entrySearch;
        $this->branchEntrySearch = $branchEntrySearch;
        $this->entryStats = $entryStats;
        $this->branchEntryStats = $branchEntryStats;

        parent::__construct($request, $apiRequest, $apiResponse);

    }

    /**
     * Archive an entry
     *
     * @param ArchiveValidator $archiveValidator
     * @param EntryStructure $entryStructure
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(ArchiveValidator $archiveValidator, EntryStructure $entryStructure)
    {

        // Check if the api request has any errors
        if ($this->apiRequest->hasErrors()) {
            $this->errors = $this->apiRequest->errors();
            EC5Logger::error('Archive Api request error', $this->requestedProject, $this->errors);
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        $data = $this->apiRequest->getData();
        // Validate the $data
        $archiveValidator->validate($data);
        if ($archiveValidator->hasErrors()) {
            EC5Logger::error('Archive Validation error', $this->requestedProject, $archiveValidator->errors());
            return $this->apiResponse->errorResponse(400, $archiveValidator->errors());
        }

        // Load an entry structure
        $entryStructure->createStructure($data);
        // Add project id to entry structure
        $entryStructure->setProjectId($this->requestedProject->getId());

        // Perform additional checks on the $entryStructure
        $archiveValidator->additionalChecks($this->requestedProject, $entryStructure);
        if ($archiveValidator->hasErrors()) {
            EC5Logger::error('Archive Validation additional error', $this->requestedProject, $archiveValidator->errors());
            return $this->apiResponse->errorResponse(400, $archiveValidator->errors());
        }

        $isBranch = !empty($entryStructure->getOwnerInputRef());

        // Options to be able to retrieve the entry
        $options = ['uuid' => $entryStructure->getEntryUuid(), 'form_ref' => $entryStructure->getFormRef()];

        // Is it a branch entry?
        if ($isBranch) {
            $archiveRepository = $this->branchEntryArchive;
            $searchRepository = $this->branchEntrySearch;
            $statsRepository = $this->branchEntryStats;
            // Add the owner_entry_uuid to the options, so we know we've been supplied the right params
            $options['owner_entry_uuid'] = $entryStructure->getOwnerUuid();
        } else {
            // Or a main entry?
            $archiveRepository = $this->entryArchive;
            $searchRepository = $this->entrySearch;
            $statsRepository = $this->entryStats;
            // Add the parent_entry_uuid to the options, so we know we've been supplied the right params
            $options['parent_entry_uuid'] = $entryStructure->getParentUuid();
        }

        // Check this $entryUuid belongs to this project
        // todo check that the entry exists given parent_uuid, branch_owner etc etc

        $entry = $searchRepository->getEntry($this->requestedProject->getId(), $options)->first();
        if (count($entry) == 0) {
            return $this->apiResponse->errorResponse(400, ['entry_archive' => ['ec5_239']]);
        }

        // Check if this user has permission to delete the entry
        if (!$this->requestedProjectRole->canDeleteEntry($entry)) {
            return $this->apiResponse->errorResponse(400, ['entry_archive' => ['ec5_91']]);
        }

        // Attempt to Archive
        if (!$archiveRepository->archive($this->requestedProject->getId(), $entryStructure->getFormRef(), $entryStructure->getEntryUuid())) {
            EC5Logger::error('Archive entry db error', $this->requestedProject, $archiveRepository->errors());
            return $this->apiResponse->errorResponse(400, $archiveRepository->errors());
        }

        // Update the entry stats
        $this->updateStats($entryStructure, $statsRepository);

        return $this->apiResponse->successResponse('ec5_236');

    }

    /**
     *
     */
    public function destroy()
    {
        //
    }

    /**
     *
     */
    public function restore()
    {
        //
    }

    /**
     * @param EntryStructure $entryStructure
     * @param $statsRepository
     */
    private function updateStats(EntryStructure $entryStructure, StatsRepository $statsRepository)
    {
        // Update the project stats counts
        if (!$statsRepository->updateProjectEntryStats($this->requestedProject)) {
            $this->errors['entry_archive'] = ['ec5_94'];
        }

        // Update additional stats
        if (!$statsRepository->updateAdditionalStats($this->requestedProject, $entryStructure)) {
            $this->errors['entry_archive'] = ['ec5_94'];
        }
    }

}