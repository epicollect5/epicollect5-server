<?php

namespace ec5\Http\Controllers\Api\Project;

use Auth;
use DB;
use ec5\Http\Validation\Entries\Upload\RuleCanBulkUpload;
use ec5\Http\Validation\Project\RuleName;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Traits\Requests\RequestAttributes;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Log;
use Response;

class ProjectController
{

    use RequestAttributes;

    /**
     * @param ProjectStats $projectStats
     * @return JsonResponse
     */
    public function show(ProjectStats $projectStats)
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
        $projectStats->updateProjectStats($this->requestedProject()->getId());

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

        $meta = [
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
        ];

        return Response::apiData($data, $meta);
    }

    /**
     * @param ProjectStats $projectStats
     * @return JsonResponse
     */
    public function export(ProjectStats $projectStats)
    {
        $data = $this->requestedProject()->getProjectDefinition()->getData();
        //todo HACK!!!, we needed to expose the creation date of a project at a later stage and this was the laziest way ;)
        $data['project']['created_at'] = $this->requestedProject()->getCreatedAt();

        //todo HACK!!!, we needed to expose the project homepage property of a project at a later stage and this was the laziest way ;)
        $homepage = config('app.url') . '/project/' . $this->requestedProject()->slug;
        $data['project']['homepage'] = $homepage;

        //todo: update project stats (a try catch need in case it does not work?)
        // Update the project stats counts
        $projectStats->updateProjectStats($this->requestedProject()->getId());

        $meta = [
            'project_mapping' => $this->requestedProject()->getProjectMapping()->getData(),
            'project_stats' => array_merge($this->requestedProject()->getProjectStats()->toArray(), [
                'structure_last_updated' => $this->requestedProject()->getProjectStats()->structure_last_updated
            ])
        ];

        return Response::apiData($data, $meta);

    }

    public function search($name = '')
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

        return Response::apiData($projects);

    }

    public function exists(RuleName $ruleName, $name)
    {
        $data['name'] = $name;
        $data['slug'] = Str::slug($name, '-');
        // Run validation
        $ruleName->validate($data);

        $data = [
            'type' => 'exists',
            'id' => $data['slug'],
            'exists' => $ruleName->hasErrors()
        ];

        return Response::apiData($data);
    }

    public function version($slug)
    {
        // If no project found, bail out
        $version = Project::version($slug);
        if (!$version) {
            $errors = ['version' => ['ec5_11']];
            return Response::apiErrorCode('500', $errors);
        }

        //return updated_at as the version
        $data = [
            'type' => 'project-version',
            'id' => $slug,
            'attributes' => [
                'structure_last_updated' => $version,//legacy
                'version' => (string)strtotime($version)
            ]

        ];
        return Response::apiData($data);
    }

    public function countersEntries($slug)
    {
        $projectStats = ProjectStats::where('project_id', $this->requestedProject()->getId())
            ->select('*') // Select all columns
            ->first();
        $totalBranches = 0;
        $branchCounts = json_decode($projectStats->branch_counts, true);
        foreach ($branchCounts as $branchCount) {
            $totalBranches += $branchCount['count'];
        }

        $data = [
            'type' => 'counters-project-entries',
            'id' => $slug,
            'counters' => [
                'total' => $totalBranches + $projectStats->total_entries,
                'entries' => $projectStats->total_entries,
                'branch_entries' => $totalBranches
            ]
        ];
        return Response::apiData($data);
    }

    public function updateCanBulkUpload(RuleCanBulkUpload $ruleCanBulkUpload)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            $errors = ['ec5_91'];
            return Response::apiErrorCode(400, ['errors' => $errors]);
        }

        // Get request params
        $params = request()->all();

        //validate params
        $ruleCanBulkUpload->validate($params);
        if ($ruleCanBulkUpload->hasErrors()) {
            return Response::apiErrorCode(400, $ruleCanBulkUpload->errors());
        }

        $canBulkUpload = $params['can_bulk_upload'];
        try {
            $project = Project::find($this->requestedProject()->getId());
            $project->can_bulk_upload = $canBulkUpload;
            $project->save();
        } catch (\Exception $e) {
            $errors = ['ec5_361'];
            return Response::apiErrorCode(400, ['errors' => $errors]);
        }

        $data = ['message' => config('epicollect.codes.ec5_362')];
        return Response::apiData($data);
    }
}
