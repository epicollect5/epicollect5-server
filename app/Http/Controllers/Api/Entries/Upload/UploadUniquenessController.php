<?php

declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\Upload;

use ec5\Http\Validation\Entries\Upload\RuleUniqueness;
use ec5\Services\Entries\EntriesUniquenessService;
use Illuminate\Http\JsonResponse;
use Log;
use Response;

class UploadUniquenessController extends UploadControllerBase
{
    /**
     * @param RuleUniqueness $ruleUniqueness
     * @return JsonResponse
     */
    public function index(RuleUniqueness $ruleUniqueness)
    {
        /* VALIDATION */
        $data = request()->get('data');
        $ruleUniqueness->validate($data);
        if ($ruleUniqueness->hasErrors()) {
            return Response::apiErrorCode(400, $ruleUniqueness->errors());
        }
        // Check project status
        if (!$this->entriesUploadService->isProjectActive()) {
            return Response::apiErrorCode(400, ['uniqueness-controller' => ['ec5_202']]);
        }

        // Build the entry
        $this->entryStructure->init(
            request()->get('data'),
            $this->requestedProject()->getId(),
            $this->requestedProjectRole()
        );

        $data = $this->entryStructure->getEntry();
        $formRef = $this->entryStructure->getFormRef();
        $inputRef = $data['input_ref'];
        $answer = $data['answer'];

        //get project definition
        $projectExtra = $this->requestedProject()->getProjectExtra();

        // Get form ref
        $form = $projectExtra->getFormDetails($formRef);
        if (count($form) == 0) {
            return Response::apiErrorCode(400, ['uniqueness-controller' => ['ec5_15']]);
        }
        // Get input
        $input = $projectExtra->getInputData($inputRef);
        if (!$input) {
            return Response::apiErrorCode(400, ['uniqueness-controller' => ['ec5_84']]);
        }

        // Get the uniqueness type
        $uniquenessType = $input['uniqueness'];
        if (!$uniquenessType) {
            Log::error('Uniqueness not set!', ['project' => $this->requestedProject()]);
            return Response::apiErrorCode(400, ['uniqueness-controller' => ['ec5_22']]);
        }

        $inputType = $input['type'];
        $inputDatetimeFormat = $input['datetime_format'];

        // Check if the answer is unique or not
        $entryService = new EntriesUniquenessService();
        if (!$entryService->isUnique($this->entryStructure, $uniquenessType, $inputRef, $answer, $inputType, $inputDatetimeFormat)) {
            return Response::apiErrorCode(400, ['uniqueness-controller' => ['ec5_22']]);
        }

        return Response::apiSuccessCode('ec5_249');
    }
}
