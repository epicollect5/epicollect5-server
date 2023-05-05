<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use ec5\Http\Controllers\Api\ApiResponse;

use ec5\Http\Validation\Project\Mapping\RuleMappingCreate as MappingCreateValidator;
use ec5\Http\Validation\Project\Mapping\RuleMappingDelete as MappingDeleteValidator;
use ec5\Http\Validation\Project\Mapping\RuleMappingUpdate as MappingUpdateValidator;
use ec5\Http\Validation\Project\Mapping\RuleMappingStructure as MappingStructureValidator;

use ec5\Repositories\QueryBuilder\Project\UpdateRepository as ProjectUpdate;

class ProjectMappingController extends ProjectControllerBase
{

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show()
    {
        if (!$this->requestedProjectRole->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $vars = $this->defaultProjectDetailsParams('mapping', '');
        $vars['mappingJson'] = $this->requestedProject->getProjectMapping()->getData();

        return view('project.project_details', $vars);
    }

    /**
     * @param ApiResponse $apiResponse
     * @param ProjectUpdate $projectUpdate
     * @param MappingCreateValidator $mappingCreateValidator
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function store(ApiResponse $apiResponse, ProjectUpdate $projectUpdate, MappingCreateValidator $mappingCreateValidator)
    {

        if (!$this->requestedProjectRole->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $projectMapping = $this->requestedProject->getProjectMapping();
        $inputs = $this->request->all();

        // Validate
        $mappingCreateValidator->validate($inputs);
        if ($mappingCreateValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingCreateValidator->errors());
        }
        // Check for additional
        $mappingCreateValidator->additionalChecks($projectMapping, $inputs);
        if ($mappingCreateValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingCreateValidator->errors());
        }

        // Create the new custom map
        $projectMapping->createCustomMap($inputs);
        // Attempt to insert into the database
        $tryUpdate = $projectUpdate->updateProjectStructure($this->requestedProject);
        if ($tryUpdate) {

            $apiResponse->setData([
                'map_index' => $projectMapping->getLastMapIndex(),
                'mapping' => $projectMapping->getData()
            ]);

            return $apiResponse->toJsonResponse('200', $options = 0);
        }

        // DB insert error
        return $apiResponse->errorResponse(400, ['errors' => ['ec5_116']]);
    }

    /**
     * @param ApiResponse $apiResponse
     * @param ProjectUpdate $projectUpdate
     * @param MappingDeleteValidator $mappingDeleteValidator
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function delete(ApiResponse $apiResponse, ProjectUpdate $projectUpdate, MappingDeleteValidator $mappingDeleteValidator)
    {
        if (!$this->requestedProjectRole->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $projectMapping = $this->requestedProject->getProjectMapping();
        $inputs = $this->request->all();

        // Validate
        $mappingDeleteValidator->validate($inputs);
        if ($mappingDeleteValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingDeleteValidator->errors());
        }
        // Check for additional
        $mappingDeleteValidator->additionalChecks($projectMapping, $inputs);
        if ($mappingDeleteValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingDeleteValidator->errors());
        }

        // Delete the map
        $projectMapping->deleteMap($inputs['map_index']);
        // Attempt to insert into the database
        $tryUpdate = $projectUpdate->updateProjectStructure($this->requestedProject);
        if ($tryUpdate) {
            $apiResponse->setData([
                'mapping' => $projectMapping->getData()
            ]);

            return $apiResponse->toJsonResponse('200', $options = 0);
        }

        // DB insert error
        return $apiResponse->errorResponse(400, ['errors' => ['ec5_116']]);
    }

    /**
     * @param ApiResponse $apiResponse
     * @param ProjectUpdate $projectUpdate
     * @param MappingStructureValidator $mappingStructureValidator
     * @param MappingUpdateValidator $mappingUpdateValidator
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function update(
        ApiResponse $apiResponse,
        ProjectUpdate $projectUpdate,
        MappingStructureValidator $mappingStructureValidator,
        MappingUpdateValidator $mappingUpdateValidator
    ) {
        if (!$this->requestedProjectRole->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $projectMapping = $this->requestedProject->getProjectMapping();
        $inputs = $this->request->all();

        // Validate
        $mappingUpdateValidator->validate($inputs);

        if ($mappingUpdateValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingUpdateValidator->errors());
        }
        // Check for additional
        $mappingUpdateValidator->additionalChecks($projectMapping, $inputs);
        if ($mappingUpdateValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingUpdateValidator->errors());
        }

        // Determine the edit action
        switch ($inputs['action']) {
            case 'make-default':
                // Make this map default
                $projectMapping->setDefault($inputs['map_index']);
                break;
            case 'rename':
                // Rename this map
                $projectMapping->renameMap($inputs['map_index'], $inputs['name']);
                break;
            case 'update':

                // Validate
                $mappingStructureValidator->validate($inputs['mapping']);
                if ($mappingStructureValidator->hasErrors()) {
                    return $apiResponse->errorResponse(422, $mappingStructureValidator->errors());
                }
                // Check additional (validate the mapping structure)
                $mappingStructureValidator->additionalChecks($this->requestedProject, $inputs['mapping']);
                if ($mappingStructureValidator->hasErrors()) {
                    return $apiResponse->errorResponse(422, $mappingStructureValidator->errors());
                }

                // Update the entire map
                $projectMapping->updateMap($inputs['map_index'], $inputs['mapping']);
                break;
        }

        // Attempt to insert into the database
        $tryUpdate = $projectUpdate->updateProjectStructure($this->requestedProject);
        if ($tryUpdate) {

            $apiResponse->setData([
                'mapping' => $projectMapping->getData()
            ]);

            return $apiResponse->toJsonResponse('200', $options = 0);
        }

        // DB insert error
        return $apiResponse->errorResponse(400, ['errors' => ['ec5_116']]);
    }
}
