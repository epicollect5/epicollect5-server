<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Controllers\Api\ApiResponse as ApiResponse;
use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;
use ec5\Http\Validation\Entries\Upload\RuleCanBulkUpload;
use ec5\Models\Eloquent\Project;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository as EntryStatsRepository;
use Exception;
use Auth;

class ProjectController extends ProjectControllerBase
{

    /**
     * @param ApiResponse $apiResponse
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(ApiResponse $apiResponse, EntryStatsRepository $entryStatsRepository)
    {
        $data = $this->requestedProject->getProjectDefinition()->getData();

        //todo HACK!!!, we needed to expose the creation date of a project at a later stage and this was the laziest way ;)
        $data['project']['created_at'] = $this->requestedProject->getCreatedAt();

        //todo HACK!!!, we needed to expose the can_bulk_upload property of a project at a later stage and this was the laziest way ;)
        $data['project']['can_bulk_upload'] = $this->requestedProject->getCanBulkUpload();

        //todo HACK!!!, we needed to expose the project homepage property of a project at a later stage and this was the laziest way ;)
        $homepage = config('app.url') . '/project/' . $this->requestedProject->slug;
        $data['project']['homepage'] = $homepage;

        $apiResponse->setData($data);

        $projectExtra = $this->requestedProject->getProjectExtra()->getData();

        // Update the project stats counts
        //todo: (a try catch need in case it does not work?)
        $entryStatsRepository->updateProjectEntryStats($this->requestedProject);

        $userName = '';
        $userAvatar = '';
        try {
            $userName = Auth::user()->name;
            $userAvatar = Auth::user()->avatar;
            //passwordless and apple auth do not get avatar, set placeholder
            if (empty($userAvatar)) {
                $userAvatar = env('APP_URL') . '/images/avatar-placeholder.png';
            }
        } catch (Exception $e) {
            //
            $userName = 'User';
            $userAvatar = env('APP_URL') . '/images/avatar-placeholder.png';
        }

        $apiResponse->setMeta([
            'project_extra' => $projectExtra,
            'project_user' => [
                'name' => $userName,
                'avatar' => $userAvatar,
                'role' => $this->requestedProjectRole->getRole(),
                'id' => $this->requestedProjectRole->getUser()->id ?? null,
            ],
            'project_mapping' => $this->requestedProject->getProjectMapping()->getData(),
            'project_stats' => array_merge($this->requestedProject->getProjectStats()->getData(), [
                'structure_last_updated' => $this->requestedProject->getProjectStats()->getProjectStructureLastUpdated()
            ])
        ]);

        return $apiResponse->toJsonResponse('200', $options = 0);
    }

    /**
     * @param ApiResponse $apiResponse
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(ApiResponse $apiResponse, EntryStatsRepository $entryStatsRepository)
    {
        $data = $this->requestedProject->getProjectDefinition()->getData();
        //todo HACK!!!, we needed to expose the creation date of a project at a later stage and this was the laziest way ;)
        $data['project']['created_at'] = $this->requestedProject->getCreatedAt();

        //todo HACK!!!, we needed to expose the project homepage property of a project at a later stage and this was the laziest way ;)
        $homepage = config('app.url') . '/project/' . $this->requestedProject->slug;
        $data['project']['homepage'] = $homepage;

        $apiResponse->setData($data);

        //todo: update project stats (a try catch need in case it does not work?)
        // Update the project stats counts
        $entryStatsRepository->updateProjectEntryStats($this->requestedProject);
        \Log::info('ProjectController export() calls updateProjectEntryStats()');

        $apiResponse->setMeta([
            'project_mapping' => $this->requestedProject->getProjectMapping()->getData(),
            'project_stats' => array_merge($this->requestedProject->getProjectStats()->getData(), [
                'structure_last_updated' => $this->requestedProject->getProjectStats()->getProjectStructureLastUpdated()
            ])
        ]);

        return $apiResponse->toJsonResponse('200', $options = 0);
    }

    public function updateCanBulkUpload(Request $request, ApiResponse $apiResponse, RuleCanBulkUpload $validator)
    {

        if (!$this->requestedProjectRole->canEditProject()) {
            $errors = ['ec5_91'];
            return $apiResponse->errorResponse(400, ['errors' => $errors]);
        }

        // Get request params
        $params = $request->all();

        //validate params
        $validator->validate($params);
        if ($validator->hasErrors()) {
            return $apiResponse->errorResponse(400, $validator->errors());
        }

        $canBulkUpload = $params['can_bulk_upload'];
        try {
            $project = Project::find($this->requestedProject->getId());
            $project->can_bulk_upload = $canBulkUpload;
            $project->save();
        } catch (\Exception $e) {
            $errors = ['ec5_361'];
            return $apiResponse->errorResponse(400, ['errors' => $errors]);
        }

        $apiResponse->setData(['message' => trans('status_codes.ec5_362')]);
        return $apiResponse->toJsonResponse(200);
    }
}
