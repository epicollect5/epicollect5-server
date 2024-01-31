<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Controllers\Api\ApiResponse as ApiResponse;
use ec5\Http\Validation\Project\RuleProjectDefinition;
use ec5\Libraries\Utilities\Arrays;
use ec5\Libraries\Utilities\Strings;
use ec5\Models\Project\ProjectStructure;
use ec5\Traits\Requests\RequestAttributes;
use Exception;
use Illuminate\Http\Request;
use Log;

class FormBuilderController
{
    use RequestAttributes;

    protected $request;
    protected $apiResponse;
    protected $ruleProjectDefinition;

    public function __construct(
        Request               $request,
        RuleProjectDefinition $ruleProjectDefinition,
        ApiResponse           $apiResponse
    )
    {
        $this->request = $request;
        $this->apiResponse = $apiResponse;
        $this->ruleProjectDefinition = $ruleProjectDefinition;
    }

    public function store()
    {
        //unpack posted project definition which is gzipped and base64 encoded
        try {
            $requestContent = json_decode(gzdecode(base64_decode($this->request->getContent())), true);
        } catch (Exception $e) {
            Log::error('Formbuilder decoding failed', [
                'exception' => $e->getMessage()
            ]);
            return $this->apiResponse->errorResponse('422', ['Request' => ['ec5_62']]);
        }

        //no data property? bail out
        if (!isset($requestContent['data'])) {
            return $this->apiResponse->errorResponse('422', ['Request' => ['ec5_14']]);
        }

        $projectDefinition = $requestContent['data'];

        //do we have permissions to edit the project?
        if (!$this->requestedProjectRole()->canEditProject()) {
            return $this->apiResponse->errorResponse('422', ['Validation' => ['ec5_91']]);
        }

        // Check for any errors so far
        if (empty($projectDefinition)) {
            return $this->apiResponse->errorResponse('422', ['Validation' => ['ec5_62']]);
        }

        //*********************************************************
        //todo fix the following as it is duplicated on ApiRequest
        // Implode as string so we can use regex
        $stringifiedProjectDefinition = Arrays::implodeMulti($projectDefinition);

        // If preg_match returns 1 or false, error out
        // 0 means no matches, which is the only case allowed

        // Check for HTML
        if (Strings::containsHtml($stringifiedProjectDefinition)) {
            return $this->apiResponse->errorResponse('422', ['json-contains-html' => ['ec5_220']]);
        }

        // Check for emoji
        if (Strings::containsEmoji($stringifiedProjectDefinition)) {
            return $this->apiResponse->errorResponse('422', ['json-contains-emoji' => ['ec5_323']]);
        }
        //todo end of what is duplicated
        //***********************************************************

        // Add Project Definition
        $this->requestedProject()->addProjectDefinition($projectDefinition);

        // Validate and generate Project Extra from Project Definition
        $this->ruleProjectDefinition->validate($this->requestedProject());

        // Check for any errors so far
        if ($this->ruleProjectDefinition->hasErrors()) {
            return $this->apiResponse->errorResponse('422', $this->ruleProjectDefinition->errors());
        }
        // Update Project Mappings
        $this->requestedProject()->updateProjectMappings();
        // Update structures, set updated_at field to true
        if (!ProjectStructure::updateStructures($this->requestedProject(), true)) {
            return $this->apiResponse->errorResponse('422', ['DB' => ['ec5_116']]);
        }
        // Return the Project Definition data in the response
        $this->apiResponse->setData($this->requestedProject()->getProjectDefinition()->getData());
        return $this->apiResponse->toJsonResponse('200', $options = 0);
    }
}
