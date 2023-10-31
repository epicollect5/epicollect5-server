<?php

declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\Upload;


use ec5\Http\Validation\Entries\Upload\RuleUniqueness as UniquenessValidator;

use ec5\Repositories\QueryBuilder\Entry\Upload\Search\EntryRepository as EntrySearchRepository;
use ec5\Repositories\QueryBuilder\Entry\Upload\Search\BranchEntryRepository as BranchEntrySearchRepository;

class UniquenessController extends UploadControllerBase
{

    /**
     * @param UniquenessValidator $uniquenessValidator
     * @param EntrySearchRepository $entrySearchRepository
     * @param BranchEntrySearchRepository $branchEntrySearchRepository
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(
        UniquenessValidator         $uniquenessValidator,
        EntrySearchRepository       $entrySearchRepository,
        BranchEntrySearchRepository $branchEntrySearchRepository
    )
    {

        /* API REQUEST VALIDATION */
        if (!$this->isValidApiRequest()) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }


        /* VALIDATION */
        $uniquenessValidator->validate($uploadValidator);
        if ($uniquenessValidator->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }


        // Check project status
        if (!$this->hasValidProjectStatus()) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        // Build the entry
        $this->buildEntryStructure();

        $data = $this->entryStructure->getEntry();
        $formRef = $this->entryStructure->getFormRef();
        $inputRef = $data['input_ref'];
        $answer = $data['answer'];

        //get project definition
        $projectExtra = $this->requestedProject->getProjectExtra();

        // Get form ref
        $form = $projectExtra->getFormDetails($formRef);
        if (count($form) == 0) {
            return $this->apiResponse->errorResponse(400, ['upload-controller' => ['ec5_15']]);
        }

        // Get input
        $input = $projectExtra->getInputData($inputRef);
        if (!$input) {
            return $this->apiResponse->errorResponse(400, ['upload-controller' => ['ec5_84']]);
        }

        // Get uniqueness type
        $uniquenessType = $input['uniqueness'];
        if (!$uniquenessType) {
            \Log::error('Uniqueness not set!', $this->requestedProject, $input);
            return $this->apiResponse->errorResponse(400, ['upload-controller' => ['ec5_22']]);
        }

        $inputType = $input['type'];
        $inputDatetimeFormat = $input['datetime_format'];


        // Set search repository
        $searchRepository = $entrySearchRepository;

        // If this is from a branch
        if ($this->entryStructure->getOwnerInputRef()) {
            $this->entryStructure->setAsBranch();
            $searchRepository = $branchEntrySearchRepository;
        }

        // Check if the answer is unique or not
        if (!$searchRepository->isUnique($this->entryStructure, $uniquenessType, $inputRef, $answer, $inputType, $inputDatetimeFormat)) {
            return $this->apiResponse->errorResponse(400, ['upload-controller' => ['ec5_22']]);
        }

        return $this->apiResponse->successResponse('ec5_249');
    }
}
