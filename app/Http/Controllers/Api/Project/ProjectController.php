<?php

namespace ec5\Http\Controllers\Api\Project;

use Auth;
use Carbon\Carbon;
use ec5\Http\Validation\Entries\Upload\RuleCanBulkUpload;
use ec5\Http\Validation\Project\RuleName;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Services\Media\MediaCounterService;
use ec5\Services\Project\ProjectLogoService;
use ec5\Traits\Eloquent\StatsRefresher;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Response;
use Throwable;

class ProjectController
{
    use StatsRefresher;
    use RequestAttributes;

    /**
     * @return JsonResponse
     * @throws Throwable
     */
    public function show()
    {
        // Project metadata responses include project_stats, so refresh and reload the DTO first.
        $this->refreshProjectStats($this->requestedProject());

        $data = $this->requestedProject()->getProjectDefinition()->getData();

        //HACK:, we needed to expose the creation date of a project at a later stage, and this was the laziest way ;)
        $data['project']['created_at'] = $this->requestedProject()->getCreatedAt();

        //HACK:, we needed to expose the can_bulk_upload property of a project at a later stage, and this was the laziest way ;)
        $data['project']['can_bulk_upload'] = $this->requestedProject()->getCanBulkUpload();

        //HACK:, we needed to expose the project homepage property of a project at a later stage, and this was the laziest way ;)
        $homepage = config('app.url') . '/project/' . $this->requestedProject()->slug;
        $data['project']['homepage'] = $homepage;

        $projectExtra = $this->requestedProject()->getProjectExtra()->getData();

        try {
            $userName = Auth::user()->name;
            $userAvatar = Auth::user()->avatar;
            //passwordless and apple auth do not get avatar, set placeholder
            if (empty($userAvatar)) {
                $userAvatar = config('app.url') . '/images/avatar-placeholder.png';
            }
        } catch (Throwable) {
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
     * @return JsonResponse
     * @throws Throwable
     */
    public function export()
    {
        // Project export is not paginated and includes project_stats metadata.
        $this->refreshProjectStats($this->requestedProject());

        $data = $this->requestedProject()->getProjectDefinition()->getData();
        //todo HACK!!!, we needed to expose the creation date of a project at a later stage and this was the laziest way ;)
        $data['project']['created_at'] = $this->requestedProject()->getCreatedAt();

        //todo HACK!!!, we needed to expose the project homepage property of a project at a later stage and this was the laziest way ;)
        $homepage = config('app.url') . '/project/' . $this->requestedProject()->slug;
        $data['project']['homepage'] = $homepage;

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

        $exactMatch = request()->query('exact', false);
        $logoBase64Enabled = (bool) config(
            'epicollect.setup.api.project_search_mobile_logo_base64_enabled',
            false
        );

        if (!empty($name)) {
            $columns = $logoBase64Enabled
                ? ['projects.name', 'projects.slug', 'projects.access', 'projects.ref', 'projects.logo_url']
                : ['name', 'slug', 'access', 'ref'];

            if ($exactMatch) {
                $hits = Project::matches($name, $columns);
            } else {
                $hits = Project::startsWith($name, $columns);
            }
        }

        if ($logoBase64Enabled) {
            $this->buildProjectsWithLogoBase64($hits, $projects);
        } else {
            foreach ($hits as $hit) {
                unset($hit->structure_last_updated);

                $data['type'] = 'project';
                $data['id'] = $hit->ref;
                $data['project'] = $hit;
                $projects[] = $data;
            }
        }

        return Response::apiData($projects);
    }

    private function buildProjectsWithLogoBase64(iterable $hits, array &$projects): void
    {
        $logoService = new ProjectLogoService();
        $dimensions = config('epicollect.media.project_mobile_logo');
        $privateAccess = config('epicollect.strings.project_access.private');
        $ttlDays = (int) config('epicollect.setup.system.cache.project_mobile_logo_cache_ttl_days', 365);
        $negativeTtlMinutes = (int) config('epicollect.setup.system.cache.project_mobile_logo_missing_ttl_minutes', 60);

        foreach ($hits as $hit) {
            if ($hit->access === $privateAccess) {
                $hit->logo_base64 = null;
            } elseif (empty($hit->logo_url)) {
                $hit->logo_base64 = null;
            } elseif (!empty($hit->structure_last_updated)) {
                $version = Carbon::parse($hit->structure_last_updated)->getTimestamp();
                $positiveKey = 'project_mobile_logo_base64:' . $hit->ref . ':version:' . $version;
                $negativeKey = 'project_mobile_logo_missing:' . $hit->ref . ':version:' . $version;

                $logoBase64 = Cache::get($positiveKey);

                if ($logoBase64 === null && !Cache::has($negativeKey)) {
                    $logoBase64 = $logoService->generate(
                        $hit->ref,
                        $dimensions[0],
                        $dimensions[1],
                        quality: 75
                    );

                    if ($logoBase64 === null) {
                        Cache::put($negativeKey, true, now()->addMinutes($negativeTtlMinutes));
                    } else {
                        Cache::put($positiveKey, $logoBase64, now()->addDays($ttlDays));
                    }
                }

                $hit->logo_base64 = $logoBase64;
            } else {
                $hit->logo_base64 = null;
            }

            unset($hit->structure_last_updated);
            unset($hit->logo_url);

            $data['type'] = 'project';
            $data['id'] = $hit->ref;
            $data['project'] = $hit;
            $projects[] = $data;
        }
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
            return Response::apiErrorCode('400', $errors);
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

    /**
     * @throws Throwable
     */
    public function countersEntries($slug)
    {
        // Entry totals are cached in project_stats and are not updated on each upload.
        // Refresh here because this endpoint explicitly returns those totals.
        $this->refreshProjectStats($this->requestedProject());

        $projectStats = $this->requestedProject()->getProjectStats();
        $totalBranches = 0;
        $branchCounts = $projectStats->getBranchCounts();
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

    public function countersMedia()
    {
        $mediaCounterService = new MediaCounterService();

        $counters = $mediaCounterService->computeMediaMetrics(
            $this->requestedProject()->getId(),
            $this->requestedProject()->ref
        );

        //adjust total bytes in project stats, in case it was not updated correctly
        ProjectStats::where('project_id', $this->requestedProject()->getId())
            ->update(['total_bytes' => $counters['sizes']['total_bytes']]);

        return Response::apiData($counters);
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
        } catch (Throwable) {
            $errors = ['ec5_361'];
            return Response::apiErrorCode(400, ['errors' => $errors]);
        }

        $data = ['message' => config('epicollect.codes.ec5_362')];
        return Response::apiData($data);
    }
}
