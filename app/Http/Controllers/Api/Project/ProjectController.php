<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Controllers\Api\ApiResponse as ApiResponse;
use ec5\Http\Validation\Project\RuleName;
use ec5\Models\Eloquent\ProjectStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ec5\Http\Validation\Entries\Upload\RuleCanBulkUpload;
use ec5\Models\Eloquent\Project;
use Exception;
use Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Log;
use ec5\Traits\Requests\RequestAttributes;

class ProjectController
{

    use RequestAttributes;

    /**
     * @param ApiResponse $apiResponse
     * @param ProjectStats $projectStats
     * @return JsonResponse
     */
    public function show(ApiResponse $apiResponse, ProjectStats $projectStats)
    {
        $data = $this->requestedProject()->getProjectDefinition()->getData();

        //HACK:, we needed to expose the creation date of a project at a later stage, and this was the laziest way ;)
        $data['project']['created_at'] = $this->requestedProject()->getCreatedAt();

        //HACK:, we needed to expose the can_bulk_upload property of a project at a later stage, and this was the laziest way ;)
        $data['project']['can_bulk_upload'] = $this->requestedProject()->getCanBulkUpload();

        //HACK:, we needed to expose the project homepage property of a project at a later stage, and this was the laziest way ;)
        $homepage = config('app.url') . '/project/' . $this->requestedProject()->slug;
        $data['project']['homepage'] = $homepage;


        $projectExtra = $this->requestedProject()->getProjectExtra()->getData();

        // Update the project stats counts
        //todo: (a try catch need in case it does not work?)
        $projectStats->updateProjectStats($this->requestedProject()->getId());

        $userName = '';
        $userAvatar = '';
        try {
            $userName = Auth::user()->name;
            $userAvatar = Auth::user()->avatar;
            //passwordless and apple auth do not get avatar, set placeholder
            if (empty($userAvatar)) {
                $userAvatar = config('app.url') . '/images/avatar-placeholder.png';
            }
        } catch (Exception $e) {
            //
            $userName = 'User';
            $userAvatar = config('app.url') . '/images/avatar-placeholder.png';
        }

        $apiResponse->setMeta([
            'project_extra' => $projectExtra,
            'project_user' => [
                'name' => $userName,
                'avatar' => $userAvatar,
                'role' => $this->requestedProjectRole()->getRole(),
                'id' => $this->requestedProjectRole()->getUser()->id ?? null,
            ],
            'project_mapping' => $this->requestedProject()->getProjectMapping()->getData(),
            'project_stats' => array_merge($this->requestedProject()->getProjectStats()->toArray(), [
                'structure_last_updated' => $this->requestedProject()->getProjectStats()->structure_last_updated
            ])
        ]);
        $apiResponse->setData($data);

        return $apiResponse->toJsonResponse('200', $options = 0);
    }

    /**
     * @param ApiResponse $apiResponse
     * @param ProjectStats $projectStats
     * @return JsonResponse
     */
    public function export(ApiResponse $apiResponse, ProjectStats $projectStats)
    {
        $data = $this->requestedProject()->getProjectDefinition()->getData();
        //todo HACK!!!, we needed to expose the creation date of a project at a later stage and this was the laziest way ;)
        $data['project']['created_at'] = $this->requestedProject()->getCreatedAt();

        //todo HACK!!!, we needed to expose the project homepage property of a project at a later stage and this was the laziest way ;)
        $homepage = config('app.url') . '/project/' . $this->requestedProject()->slug;
        $data['project']['homepage'] = $homepage;

        $apiResponse->setData($data);

        //todo: update project stats (a try catch need in case it does not work?)
        // Update the project stats counts
        $projectStats->updateProjectStats($this->requestedProject()->getId());
        Log::info('ProjectController export() calls updateProjectEntryStats()');

        $apiResponse->setMeta([
            'project_mapping' => $this->requestedProject()->getProjectMapping()->getData(),
            'project_stats' => array_merge($this->requestedProject()->getProjectStats()->toArray(), [
                'structure_last_updated' => $this->requestedProject()->getProjectStats()->structure_last_updated
            ])
        ]);

        return $apiResponse->toJsonResponse('200', $options = 0);
    }

    public function search(ApiResponse $apiResponse, $name = '')
    {
        $hits = [];
        $projects = [];

        if (!empty($name)) {
            //get all projects where the name starts with the needle provided (archived and trashed are filtered)
            $hits = Project::startsWith($name, ['name', 'slug', 'access', 'ref']);
        }
        // Build the json api response
        foreach ($hits as $hit) {
            $data['type'] = 'project';
            $data['id'] = $hit->ref;
            $data['project'] = $hit;
            $projects[] = $data;
        }
        // Set the data
        $apiResponse->setData($projects);

        return $apiResponse->toJsonResponse('200', $options = 0);
    }

    public function exists(ApiResponse $apiResponse, RuleName $ruleName, $name)
    {
        $data['name'] = $name;
        $data['slug'] = Str::slug($name, '-');
        // Run validation
        $ruleName->validate($data);
        // todo should type, id, attributes be setter methods in ApiResponse ?
        $apiResponse->setData([
            'type' => 'exists',
            'id' => $data['slug'],
            'exists' => $ruleName->hasErrors()
        ]);
        return $apiResponse->toJsonResponse('200', $options = 0);
    }

    public function version(ApiResponse $apiResponse, $slug)
    {
        // If no project found, bail out
        $version = Project::version($slug);
        if (!$version) {
            $errors = ['version' => ['ec5_11']];
            return $apiResponse->errorResponse('500', $errors);
        }

        //return updated_at as the version
        $apiResponse->setData([
            'type' => 'project-version',
            'id' => $slug,
            'attributes' => [
                'structure_last_updated' => $version,//legacy
                'version' => (string)strtotime($version)
            ]

        ]);
        return $apiResponse->toJsonResponse('200', $options = 0);
    }

    public function updateCanBulkUpload(Request $request, ApiResponse $apiResponse, RuleCanBulkUpload $ruleCanBulkUpload)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            $errors = ['ec5_91'];
            return $apiResponse->errorResponse(400, ['errors' => $errors]);
        }

        // Get request params
        $params = $request->all();

        //validate params
        $ruleCanBulkUpload->validate($params);
        if ($ruleCanBulkUpload->hasErrors()) {
            return $apiResponse->errorResponse(400, $ruleCanBulkUpload->errors());
        }

        $canBulkUpload = $params['can_bulk_upload'];
        try {
            $project = Project::find($this->requestedProject()->getId());
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
