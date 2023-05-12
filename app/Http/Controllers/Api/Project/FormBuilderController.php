<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use ec5\Libraries\EC5Logger\EC5Logger;
use Illuminate\Http\Request;
use ec5\Http\Validation\Project\RuleProjectDefinition as ProjectDefinitionValidator;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as ProjectUpdate;
use ec5\Http\Controllers\Api\ApiResponse as ApiResponse;
use ec5\Libraries\Utilities\Arrays;
use ec5\Libraries\Utilities\Strings;

class FormBuilderController extends ProjectControllerBase
{
    protected $projectUpdate;
    protected $request;
    protected $apiResponse;
    protected $projectDefinitionValidator;

    /**
     * FormBuilderController constructor.
     *
     * @param Request $request
     * @param ProjectUpdate $projectUpdate
     * @param ProjectDefinitionValidator $projectDefinitionValidator
     * @param ApiResponse $apiResponse
     */
    public function __construct(
        Request $request,
        ProjectUpdate $projectUpdate,
        ProjectDefinitionValidator $projectDefinitionValidator,
        ApiResponse $apiResponse
    ) {
        $this->request = $request;
        $this->apiResponse = $apiResponse;
        $this->projectUpdate = $projectUpdate;
        $this->projectDefinitionValidator = $projectDefinitionValidator;

        parent::__construct($request);
    }

    /**
     * @return $this
     */
    public function store()
    {
        // Get posted Project Definition which is gzipped and base64 encoded
        try {
            $requestContent = json_decode(gzdecode(base64_decode($this->request->getContent())), true);
        } catch (\Exception $e) {
            return $this->apiResponse->errorResponse('422', ['Request' => ['ec5_62']]);
        }

        //no data property? bail out
        if (!isset($requestContent['data'])) {
            return $this->apiResponse->errorResponse('422', ['Request' => ['ec5_14']]);
        }

        $projectDefinition = $requestContent['data'];

        //do we have permissions to edit the project?
        if (!$this->requestedProjectRole->canEditProject()) {
            EC5Logger::error('Formbuilder structure failed - user cant edit project', $this->requestedProject);
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
        $this->requestedProject->addProjectDefinition($projectDefinition);

        // Validate and generate Project Extra from Project Definition
        $this->projectDefinitionValidator->validate($this->requestedProject);

        // Check for any errors so far
        if ($this->projectDefinitionValidator->hasErrors()) {
            EC5Logger::error(
                'Formbuilder structure failed - validation',
                $this->requestedProject,
                $this->projectDefinitionValidator->errors()
            );

            return $this->apiResponse->errorResponse('422', $this->projectDefinitionValidator->errors());
        }

        // Update Project Mappings
        $this->requestedProject->updateProjectMappings();

        // Set updated_at field to true

        $tryAction = $this->projectUpdate->updateProjectStructure($this->requestedProject, true);

        if ($tryAction) {
            EC5Logger::info('Storing Formbuilder structure successful', $this->requestedProject);
            // Return the Project Definition data in the response
            $this->apiResponse->setData($this->requestedProject->getProjectDefinition()->getData());
            return $this->apiResponse->toJsonResponse('200', $options = 0);
        }

        EC5Logger::error('Formbuilder structure failed - could not save into db', $this->requestedProject, $this->projectUpdate->errors());
        return $this->apiResponse->errorResponse('422', ['DB' => ['ec5_116']]);
    }
}
