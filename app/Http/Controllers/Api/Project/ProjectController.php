<?php

namespace ec5\Http\Controllers\Api\Project;

use Auth;
use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\Entries\Upload\RuleCanBulkUpload;
use ec5\Http\Validation\Project\Mapping\RuleImportProjectMapping as ImportProjectMappingValidator;
use ec5\Http\Validation\Project\RuleImportJson as ImportJsonValidator;
use ec5\Http\Validation\Project\RuleName;
use ec5\Http\Validation\Project\RuleProjectDefinition as ProjectDefinitionValidator;
use ec5\Http\Validation\Schemas\ProjectSchemaValidator;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Services\Media\MediaCounterService;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Log;
use Response;
use Throwable;

class ProjectController
{
    use RequestAttributes;

    /**
     * @param ProjectStats $projectStats
     * @return JsonResponse
     * @throws Throwable
     */
    public function show(ProjectStats $projectStats)
    {
        $data = $this->getProjectResponseData(true);
        $projectStats->updateProjectStats($this->requestedProject()->getId());

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
            'project_extra' => $this->requestedProject()->getProjectExtra()->getData(),
            'project_user' => [
                'name' => $userName,
                'avatar' => $userAvatar,
                'role' => $this->requestedProjectRole()->getRole(),
                'id' => $this->requestedProjectRole()->getUser()->id ?? null,
            ],
            'project_mapping' => $this->requestedProject()->getProjectMapping()->getData(),
            'project_stats' => $this->getProjectStatsMeta(),
        ];

        return Response::apiData($data, $meta);
    }

    /**
     * @param ProjectStats $projectStats
     * @return JsonResponse
     * @throws Throwable
     */
    public function export(ProjectStats $projectStats)
    {
        $data = $this->getProjectResponseData();
        $projectStats->updateProjectStats($this->requestedProject()->getId());

        $meta = [
            'project_mapping' => $this->requestedProject()->getProjectMapping()->getData(),
            'project_stats' => $this->getProjectStatsMeta(),
        ];

        return Response::apiData($data, $meta);
    }

    private function getProjectResponseData(bool $includeCanBulkUpload = false): array
    {
        // We need to sanitise the project definition due to legacy bugs that went through over the years
        $project = $this->requestedProject();
        $data = $project->getSanitisedProjectDefinition();

        // HACK: expose fields added after the original API contract was defined.
        $data['project']['created_at'] = $project->getCreatedAt();
        $data['project']['homepage'] = config('app.url') . '/project/' . $project->slug;

        if ($includeCanBulkUpload) {
            $data['project']['can_bulk_upload'] = $project->getCanBulkUpload();
        }

        return $data;
    }

    private function getProjectStatsMeta(): array
    {
        $projectStats = $this->requestedProject()->getProjectStats();

        return array_merge($projectStats->toArray(), [
            'structure_last_updated' => $projectStats->structure_last_updated,
            'project_definition_version' => $projectStats->project_definition_version,
        ]);
    }

    public function search($name = '')
    {
        $hits = [];
        $projects = [];

        // Check if the 'exact' query parameter is present and true
        $exactMatch = request()->query('exact', false);

        if (!empty($name)) {
            if ($exactMatch) {
                // Perform exact match search
                $hits = Project::matches($name, ['name', 'slug', 'access', 'ref']);
            } else {
                // Perform starts-with search
                $hits = Project::startsWith($name, ['name', 'slug', 'access', 'ref']);
            }
        }

        // Build the JSON API response
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
            return Response::apiErrorCode('400', $errors);
        }

        //return updated_at as the version
        $data = [
            'type' => 'project-version',
            'id' => $slug,
            'attributes' => [
                'structure_last_updated' => $version, // legacy
                'project_definition_version' => (string)strtotime($version),
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

    public function validateImport(
        Request                    $request,
        ProjectDefinitionValidator $projectDefinitionValidator,
        ImportProjectMappingValidator $importProjectMappingValidator,
        ImportJsonValidator        $importJsonValidator,
        ProjectSchemaValidator     $projectSchemaValidator,
        ProjectDTO                 $projectDTO
    ) {
        // 1. Check Authorization Header
        $token = $request->bearerToken();
        $expectedToken = config('epicollect.setup.api.import_project.validation_key');

        if (!$token || !hash_equals($expectedToken, $token)) {
            return Response::apiErrorCode('400', ['error' => ['ec5_257']]);
        }

        $data = $request->post();

        // 2. Basic structure check — is the payload shaped like a project request?
        //    Checks: data required, data.type = 'project', data.project is array
        $importJsonValidator->validate($data);

        if ($importJsonValidator->hasErrors()) {
            return Response::apiErrorCode('400', $importJsonValidator->errors());
        }

        // 3. JSON Schema validation — full structural gate
        //    Validates against public/schemas/project.schema.json
        //    Checks: ref patterns, input keys, possible_answers limits,
        //    enums, string lengths, emoji/< > restrictions etc.
        if (!$projectSchemaValidator->validate($data)) {
            return Response::apiSchemaError('400', $projectSchemaValidator->schemaId(), $projectSchemaValidator->violations());
        }

        $name = data_get($data, 'data.project.name', 'Imported Project');
        $payload = [
            'name'       => $name,
            'created_by' => 0,
        ];

        // 4. Generate new project ref
        $newProjectRef = Generators::projectRef();
        $projectDefinitionData = $data['data'];

        try {
            $projectDTO->import(
                $newProjectRef,
                $payload['name'],
                $payload['created_by'],
                $projectDefinitionData,
                $projectDefinitionValidator,
                data_get($data, 'meta.project_mapping'),
                $importProjectMappingValidator
            );
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            $errors = $importProjectMappingValidator->errors();
            if (empty($errors)) {
                $errors = $projectDefinitionValidator->errors();
            }
            if (empty($errors)) {
                $errors = [
                    'validation' => ['ec5_39']
                ];
            }
            return Response::apiErrorCode('400', $errors);
        }

        return Response::apiSchemaSuccess(
            $newProjectRef,
            $payload['name'],
            $projectSchemaValidator->schemaId()
        );
    }
}
