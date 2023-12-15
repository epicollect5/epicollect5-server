<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\Api\ApiResponse;

use ec5\Http\Validation\Project\Mapping\RuleMappingCreate as MappingCreateValidator;
use ec5\Http\Validation\Project\Mapping\RuleMappingDelete as MappingDeleteValidator;
use ec5\Http\Validation\Project\Mapping\RuleMappingUpdate as MappingUpdateValidator;
use ec5\Http\Validation\Project\Mapping\RuleMappingStructure as MappingStructureValidator;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as ProjectUpdate;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ProjectMappingController
{

    use RequestAttributes;

    public function show()
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $vars['includeTemplate'] = 'mapping';
        $vars['mappingJson'] = $this->requestedProject()->getProjectMapping()->getData();

        return view('project.project_details', $vars);
    }

    /**
     * @param ApiResponse $apiResponse
     * @param ProjectUpdate $projectUpdate
     * @param MappingCreateValidator $mappingCreateValidator
     * @return Factory|Application|JsonResponse|View
     */
    public function store(ApiResponse $apiResponse, ProjectUpdate $projectUpdate, MappingCreateValidator $mappingCreateValidator)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $projectMapping = $this->requestedProject()->getProjectMapping();
        $input = request()->all();

        // Validate
        $mappingCreateValidator->validate($input);
        if ($mappingCreateValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingCreateValidator->errors());
        }
        // Check for additional
        $mappingCreateValidator->additionalChecks($projectMapping, $input);
        if ($mappingCreateValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingCreateValidator->errors());
        }

        // Create the new custom map
        $projectMapping->createCustomMap($input);
        // Attempt to insert into the database
        $tryUpdate = $projectUpdate->updateProjectStructure($this->requestedProject());
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
     * @return Factory|Application|JsonResponse|View
     */
    public function delete(ApiResponse $apiResponse, ProjectUpdate $projectUpdate, MappingDeleteValidator $mappingDeleteValidator)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $projectMapping = $this->requestedProject()->getProjectMapping();
        $input = request()->all();

        // Validate
        $mappingDeleteValidator->validate($input);
        if ($mappingDeleteValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingDeleteValidator->errors());
        }
        // Check for additional
        $mappingDeleteValidator->additionalChecks($projectMapping, $input);
        if ($mappingDeleteValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingDeleteValidator->errors());
        }

        // Delete the map
        $projectMapping->deleteMap($input['map_index']);
        // Attempt to insert into the database
        $tryUpdate = $projectUpdate->updateProjectStructure($this->requestedProject());
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
     * @return Factory|Application|JsonResponse|View
     */
    public function update(
        ApiResponse               $apiResponse,
        ProjectUpdate             $projectUpdate,
        MappingStructureValidator $mappingStructureValidator,
        MappingUpdateValidator    $mappingUpdateValidator
    )
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $projectMapping = $this->requestedProject()->getProjectMapping();
        $input = request()->all();

        // Validate
        $mappingUpdateValidator->validate($input);

        if ($mappingUpdateValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingUpdateValidator->errors());
        }
        // Check for additional
        $mappingUpdateValidator->additionalChecks($projectMapping, $input);
        if ($mappingUpdateValidator->hasErrors()) {
            return $apiResponse->errorResponse(422, $mappingUpdateValidator->errors());
        }

        // Determine the edit action
        switch ($input['action']) {
            case 'make-default':
                // Make this map default
                $projectMapping->setDefault($input['map_index']);
                break;
            case 'rename':
                // Rename this map
                $projectMapping->renameMap($input['map_index'], $input['name']);
                break;
            case 'update':

                // Validate
                $mappingStructureValidator->validate($input['mapping']);
                if ($mappingStructureValidator->hasErrors()) {
                    return $apiResponse->errorResponse(422, $mappingStructureValidator->errors());
                }
                // Check additional (validate the mapping structure)
                $mappingStructureValidator->additionalChecks($this->requestedProject(), $input['mapping']);
                if ($mappingStructureValidator->hasErrors()) {
                    return $apiResponse->errorResponse(422, $mappingStructureValidator->errors());
                }

                // Update the entire map
                $projectMapping->updateMap($input['map_index'], $input['mapping']);
                break;
        }

        // Attempt to insert into the database
        $tryUpdate = $projectUpdate->updateProjectStructure($this->requestedProject());
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
