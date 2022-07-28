<?php

declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\Upload;

use ec5\Http\Controllers\Api\ProjectApiControllerBase;
use ec5\Http\Validation\Entries\Upload\RuleUpload as UploadValidator;

use ec5\Libraries\EC5Logger\EC5Logger;
use ec5\Repositories\QueryBuilder\Entry\Upload\Create\BranchEntryRepository as BranchEntryCreateRepository;
use ec5\Repositories\QueryBuilder\Entry\Upload\Create\EntryRepository as EntryCreateRepository;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository as EntryStatsRepository;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Api\ApiRequest;

use ec5\Models\Entries\EntryStructure;

use Illuminate\Http\Request;
use Config;
use Log;


class UploadControllerBase extends ProjectApiControllerBase
{
    /*
    |--------------------------------------------------------------------------
    | Entry Upload Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the upload of entry data
    |
    */

    /**
     * @var EntryCreateRepository Object $entryCreateRepository
     */
    protected $entryCreateRepository;

    /**
     * @var BranchEntryCreateRepository Object $branchEntryCreateRepository
     */
    protected $branchEntryCreateRepository;

    /**
     * @var EntryStatsRepository
     */
    protected $entryStatsRepository;

    /**
     * @var EntryStructure $entryStructure
     */
    protected $entryStructure;
    protected $isBulkUpload;

    /**
     * UploadControllerBase constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param EntryStructure $entryStructure
     * @param EntryCreateRepository $entryCreateRepository
     * @param BranchEntryCreateRepository $branchEntryCreateRepository
     * @param EntryStatsRepository $entryStatsRepository
     */
    public function __construct(
        Request $request,
        ApiRequest $apiRequest,
        ApiResponse $apiResponse,
        EntryStructure $entryStructure,
        EntryCreateRepository $entryCreateRepository,
        BranchEntryCreateRepository $branchEntryCreateRepository,
        EntryStatsRepository $entryStatsRepository
    ) {
        $this->entryCreateRepository = $entryCreateRepository;
        $this->branchEntryCreateRepository = $branchEntryCreateRepository;
        $this->entryStructure = $entryStructure;
        $this->entryStatsRepository = $entryStatsRepository;

        parent::__construct($request, $apiRequest, $apiResponse);
    }

    /**
     * Handle an upload entry request, either an add or an edit
     *
     * @param UploadValidator $uploadValidator
     * @return bool
     */
    public function upload(UploadValidator $uploadValidator)
    {
        $data = $this->apiRequest->getData();

        // Note: errors will be output in format ['source' => ['ec5_error']]
        // Required by the ApiResponse class

        /* API REQUEST VALIDATION */
        if (!$this->isValidApiRequest()) {
            EC5Logger::error('Upload Api request error', $this->requestedProject, $this->apiRequest->errors());

            Log::error('Upload API Request Error: ', [
                'error' => $this->apiRequest->errors(),
                'data' => $data
            ]);

            return false;
        }


        /* UPLOAD VALIDATION */
        if (!$this->isValidUpload($uploadValidator)) {
            EC5Logger::error('Upload could not be validated', $this->requestedProject, $uploadValidator->errors());
            return false;
        }

        // Check project status
        if (!$this->hasValidProjectStatus()) {
            return false;
        }

        /* USER AUTHENTICATION AND PERMISSIONS CHECK FOR PRIVATE PROJECTS */
        if (!$this->userHasPermissions()) {
            EC5Logger::error('User permissions failed', $this->requestedProject);
            return false;
        }

        /* BUILD ENTRY STRUCTURE */
        $this->buildEntryStructure();

        // Check project version this entry was created with
        if (!$this->isValidProjectVersion()) {
            return false;
        }

        /* ENTRY/ANSWERS VALIDATION */
        if (!$this->isValidAdditional($uploadValidator)) {
            return false;
        }

        /* CHECK LIMITS NOT REACHED */
        //imp: this is calculated live, it does not use the project stats table
        if ($this->isEntriesLimitReached()) {
            EC5Logger::error('Entries limit reached', $this->requestedProject);
            return false;
        }

        /* INSERT ENTRY */
        if (!$this->insertedIntoDb()) {
            EC5Logger::error('Error inserting entry', $this->requestedProject, $this->errors);

            Log::error('Error inserting entry: ', [
                'error' => $this->errors,
                'data' => $data
            ]);
            return false;
        }
        return true;
    }

    /**
     * @param UploadValidator $uploadValidator
     * @return bool
     */
    protected function isValidUpload(UploadValidator $uploadValidator)
    {
        $data = $this->apiRequest->getData();
        // Validate the api upload request
        $uploadValidator->validate($data);
        if ($uploadValidator->hasErrors()) {
            $this->errors = $uploadValidator->errors();
            return false;
        }
        return true;
    }

    /**
     * @param UploadValidator $uploadValidator
     * @return bool
     */
    protected function isValidAdditional(UploadValidator $uploadValidator)
    {
        $data = $this->apiRequest->getData();
        // Do additional checks
        $uploadValidator->additionalChecks($data, $this->requestedProject, $this->entryStructure);
        if ($uploadValidator->hasErrors()) {
            $this->errors = $uploadValidator->errors();
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function isEntriesLimitReached()
    {
        // If the entry is an edit, then it's ok to allow this entry in
        if ($this->entryStructure->isEdit()) {
            return false;
        }

        // If the entry is not an edit, proceed
        $projectDefinition = $this->requestedProject->getProjectDefinition();

        // Branch or Form?
        if ($this->entryStructure->isBranch()) {
            $ref = $this->entryStructure->getOwnerInputRef();
        } else {
            $ref = $this->entryStructure->getFormRef();
        }

        //When no entries limit set, bail out
        $entriesLimit = $projectDefinition->getEntriesLimit($ref);
        if ($entriesLimit === null) {
            return false;
        }

        // Check the entries limit has not been reached for the form or branch
        if ($this->entryStructure->isBranch()) {
            $currentEntriesCount = $this->entryStatsRepository->getBranchEntryCounts(
                $this->requestedProject->getId(),
                $this->entryStructure->getFormRef(),
                $this->entryStructure->getOwnerInputRef(),
                $this->entryStructure->getOwnerUuid()
            );
        } else {
            $currentEntriesCount = $this->entryStatsRepository->getFormEntryCounts(
                $this->requestedProject->getId(),
                $this->entryStructure->getFormRef(),
                $this->entryStructure->getParentUuid()
            );
        }

        // If we haven't reached the entries limit (Add 1 to include this entry), all good
        if (($currentEntriesCount + 1) <= $entriesLimit) {
            return false;
        }

        // Entries limit reached, throw error
        $this->errors = ['upload-controller' => ['ec5_250']];
        return true;
    }

    /**
     * @return bool
     */
    protected function insertedIntoDb()
    {
        // If we have answers to insert from our upload
        if ($this->entryStructure->hasAnswers()) {

            // Entry or Branch Entry
            if ($this->entryStructure->isBranch()) {
                $repository = $this->branchEntryCreateRepository;
            } else {
                $repository = $this->entryCreateRepository;
            }

            // If we received no errors, continue to insert answers and entry
            if (!$repository->create($this->requestedProject, $this->entryStructure, $this->isBulkUpload)) {
                $this->errors = $repository->errors();
                return false;
            }
        }
        return true;
    }

    /**
     * Build Entry Structure
     */
    protected function buildEntryStructure()
    {
        $data = $this->apiRequest->getData();
        // Get the user from the middleware request
        $user = $this->requestedProjectRole->getUser();

        // Initialise entry structure based on posted data
        $this->entryStructure->createStructure($data);
        // Add user id (0 if null) to entry structure
        $this->entryStructure->setUserId(!empty($user) ? $user->id : 0);
        // Add project id to entry structure
        $this->entryStructure->setProjectId($this->requestedProject->getId());
        // Add project role to entry structure
        $this->entryStructure->setProjectRole($this->requestedProjectRole);

        // If there is a file in the request, load into the entry structure
        if ($this->request->hasFile('name')) {
            $this->entryStructure->setFile($this->request->file('name'));
        }
    }

    /**
     * @return bool
     */
    protected function isValidApiRequest()
    {
        // Check we didn't have any api request errors
        if ($this->apiRequest->hasErrors()) {
            $this->errors = $this->apiRequest->errors();
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function isValidProjectVersion()
    {
        if ($this->requestedProject->getProjectStats()->getProjectStructureLastUpdated() != $this->entryStructure->getProjectVersion()) {
            $this->errors = ['upload-controller' => ['ec5_201']];
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function hasValidProjectStatus()
    {
        if ($this->requestedProject->status != Config::get('ec5Strings.project_status.active')) {
            $this->errors = ['upload-controller' => ['ec5_202']];
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function userHasPermissions()
    {
        // Check user is permitted to upload if project is private
        if ($this->requestedProject->isPrivate() && !$this->requestedProjectRole->canUpload()) {


            //is someone posting via the POST API?
            // We check the route name, if it gets here it means it went through the auth
            //so we add the entry without assigning any user to it.
            //This type of entry will be editable only CREATOR/MANAGER/CURATOR
            if ($this->request->route()->getName() === 'private-import') {
                return true;
            }


            $this->errors = ['upload-controller' => ['ec5_71']];
            return false;
        }
        return true;
    }
}
