<?php /** @noinspection DuplicatedCode */

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Validation\Project\Mapping\RuleMappingCreate;
use ec5\Http\Validation\Project\Mapping\RuleMappingDelete;
use ec5\Http\Validation\Project\Mapping\RuleMappingStructure;
use ec5\Http\Validation\Project\Mapping\RuleMappingUpdate;
use ec5\Models\Project\ProjectStructure;
use ec5\Traits\Requests\RequestAttributes;
use Response;

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

    public function store(RuleMappingCreate $ruleMappingCreate)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $projectMapping = $this->requestedProject()->getProjectMapping();
        $payload = request()->all();

        // Validate
        $ruleMappingCreate->validate($payload);
        if ($ruleMappingCreate->hasErrors()) {
            return Response::apiErrorCode(422, $ruleMappingCreate->errors());
        }
        // Check for additional
        $ruleMappingCreate->additionalChecks($projectMapping, $payload);
        if ($ruleMappingCreate->hasErrors()) {
            return Response::apiErrorCode(422, $ruleMappingCreate->errors());
        }

        // Create the new custom map
        $projectMapping->createCustomMap($payload);

        if (!ProjectStructure::updateStructures($this->requestedProject())) {
            // DB insert error
            return Response::apiErrorCode(400, ['errors' => ['ec5_116']]);
        }

        $data = [
            'map_index' => $projectMapping->getLastMapIndex(),
            'mapping' => $projectMapping->getData()
        ];
        return Response::apiData($data);
    }

    public function delete(RuleMappingDelete $ruleMappingDelete)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $projectMapping = $this->requestedProject()->getProjectMapping();
        $payload = request()->all();

        // Validate
        $ruleMappingDelete->validate($payload);
        if ($ruleMappingDelete->hasErrors()) {
            return Response::apiErrorCode(422, $ruleMappingDelete->errors());
        }
        // Check for additional
        $ruleMappingDelete->additionalChecks($projectMapping, $payload);
        if ($ruleMappingDelete->hasErrors()) {
            return Response::apiErrorCode(422, $ruleMappingDelete->errors());
        }

        // Delete the map
        $projectMapping->deleteMapping($payload['map_index']);

        if (!ProjectStructure::updateStructures($this->requestedProject())) {
            return Response::apiErrorCode(400, ['errors' => ['ec5_116']]);
        }

        $data = [
            'mapping' => $projectMapping->getData()
        ];
        return Response::apiData($data);
    }

    /**
     */
    public function update(
        RuleMappingStructure $ruleMappingStructure,
        RuleMappingUpdate    $ruleMappingUpdate
    )
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $projectMapping = $this->requestedProject()->getProjectMapping();
        $payload = request()->all();
        // Validate
        $ruleMappingUpdate->validate($payload);
        if ($ruleMappingUpdate->hasErrors()) {
            return Response::apiErrorCode(422, $ruleMappingUpdate->errors());
        }
        // Check for additional
        $ruleMappingUpdate->additionalChecks($projectMapping, $payload);
        if ($ruleMappingUpdate->hasErrors()) {
            return Response::apiErrorCode(422, $ruleMappingUpdate->errors());
        }

        // Determine the edit action
        switch ($payload['action']) {
            case 'make-default':
                // Make this map default
                $projectMapping->setDefault($payload['map_index']);
                break;
            case 'rename':
                // Rename this map
                $projectMapping->renameMap($payload['map_index'], $payload['name']);
                break;
            case 'update':

                // Validate
                $ruleMappingStructure->validate($payload['mapping']);
                if ($ruleMappingStructure->hasErrors()) {
                    return Response::apiErrorCode(422, $ruleMappingStructure->errors());
                }
                // Check additional (validate the mapping structure)
                $ruleMappingStructure->additionalChecks($this->requestedProject(), $payload['mapping']);
                if ($ruleMappingStructure->hasErrors()) {
                    return Response::apiErrorCode(422, $ruleMappingStructure->errors());
                }

                // Update the entire map
                $projectMapping->updateMap($payload['map_index'], $payload['mapping']);
                break;
        }

        if (!ProjectStructure::updateStructures($this->requestedProject())) {
            return Response::apiErrorCode(400, ['errors' => ['ec5_116']]);
        }

        $data = [
            'mapping' => $projectMapping->getData()
        ];
        return Response::apiData($data);
    }
}
