<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Validation\Project\RuleProjectDefinition;
use ec5\Libraries\Utilities\Arrays;
use ec5\Libraries\Utilities\Strings;
use ec5\Models\Project\ProjectStructure;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Traits\Requests\RequestAttributes;
use Exception;
use Log;
use Response;

class FormBuilderController
{
    use RequestAttributes;

    public function store(RuleProjectDefinition $ruleProjectDefinition, ProjectMappingService $projectMappingService)
    {
        //unpack posted project definition which is gzipped and base64 encoded
        try {
            $requestContent = json_decode(gzdecode(base64_decode(request()->getContent())), true);
        } catch (\Throwable $e) {
            Log::error('Formbuilder decoding failed', [
                'exception' => $e->getMessage()
            ]);
            return Response::apiErrorCode(422, ['Request' => ['ec5_62']]);

        }

        //no data property? bail out
        if (!isset($requestContent['data'])) {
            return Response::apiErrorCode(422, ['Request' => ['ec5_14']]);
        }

        $projectDefinition = $requestContent['data'];

        //do we have permissions to edit the project?
        if (!$this->requestedProjectRole()->canEditProject()) {
            return Response::apiErrorCode(422, ['Validation' => ['ec5_91']]);
        }

        // Check for any errors so far
        if (empty($projectDefinition)) {
            return Response::apiErrorCode(422, ['Validation' => ['ec5_62']]);
        }

        //*********************************************************
        //todo fix the following as it is duplicated on ApiRequest
        // Implode as string so we can use regex
        $stringifiedProjectDefinition = Arrays::implodeMulti($projectDefinition);

        // If preg_match returns 1 or false, error out
        // 0 means no matches, which is the only case allowed

        // Check for HTML
        if (Strings::containsHtml($stringifiedProjectDefinition)) {
            return Response::apiErrorCode(422, ['validation' => ['ec5_220']]);
        }

        // Check for emoji
        if (Strings::containsEmoji($stringifiedProjectDefinition)) {
            return Response::apiErrorCode(422, ['validation' => ['ec5_323']]);
        }
        //todo end of what is duplicated
        //***********************************************************

        // Add Project Definition
        $this->requestedProject()->addProjectDefinition($projectDefinition);

        // Validate and generate Project Extra from Project Definition
        $ruleProjectDefinition->validate($this->requestedProject());

        // Check for any errors so far
        if ($ruleProjectDefinition->hasErrors()) {
            return Response::apiErrorCode(422, $ruleProjectDefinition->errors());
        }
        // Update Project Mappings
        $projectExtra = $this->requestedProject()->getProjectExtra();
        $this->requestedProject()->getProjectMapping()->updateMappings($projectExtra, $projectMappingService);
        // Update structures, set updated_at field to true
        if (!ProjectStructure::updateStructures($this->requestedProject(), true)) {
            return Response::apiErrorCode(422, ['DB' => ['ec5_116']]);
        }
        // Return the Project Definition data in the response
        return Response::apiData($this->requestedProject()->getProjectDefinition()->getData());
    }
}
