<?php

namespace ec5\Http\Controllers\Api\Project;

use Auth;
use Carbon\Carbon;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Http\Validation\Entries\Upload\RuleCanBulkUpload;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Http\Validation\Project\Mapping\RuleImportProjectMapping as ImportProjectMappingValidator;
use ec5\Http\Validation\Project\RuleImportJson as ImportJsonValidator;
use ec5\Http\Validation\Project\RuleName;
use ec5\Http\Validation\Project\RuleProjectDefinition as ProjectDefinitionValidator;
use ec5\Http\Validation\Schemas\ProjectSchemaValidator;
use ec5\Libraries\Utilities\DateFormatConverter;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Services\Media\MediaCounterService;
use ec5\Services\Project\ProjectLogoService;
use ec5\Traits\Eloquent\StatsRefresher;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Log;
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
        $data = $this->getProjectResponseData(true);

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
     * @return JsonResponse
     * @throws Throwable
     */
    public function export()
    {
        // Project export is not paginated and includes project_stats metadata.
        $this->refreshProjectStats($this->requestedProject());
        $data = $this->getProjectResponseData();

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

        $exactMatch = request()->query('exact', false);
        $logoBase64Enabled = (bool) config(
            'epicollect.setup.api.project_search_mobile_logo_base64_enabled',
            false
        );

        if (!empty($name)) {
            $columns = $logoBase64Enabled
                ? ['projects.name', 'projects.slug', 'projects.access', 'projects.ref', 'projects.has_logo']
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
            } elseif (empty($hit->has_logo)) {
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
            unset($hit->has_logo);

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
                'structure_last_updated' => $version, // legacy
                'project_definition_version' => DateFormatConverter::isoToUnixTimestamp($version),
                'version' => DateFormatConverter::isoToUnixTimestamp($version)
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

    public function validateImport(
        Request                    $request,
        ProjectDefinitionValidator $projectDefinitionValidator,
        ImportProjectMappingValidator $importProjectMappingValidator,
        ImportJsonValidator        $importJsonValidator,
        ProjectSchemaValidator     $projectSchemaValidator,
        ProjectDTO                 $projectDTO
    ): JsonResponse {
        // 1. Check Authorization Header
        $token = $request->bearerToken();
        $expectedToken = config('epicollect.setup.api.import_project.validation_key');
        if (!$token || !hash_equals($expectedToken, $token)) {
            return Response::apiErrorCode('400', ['error' => ['ec5_257']]);
        }

        $data = $request->all();

        // 2. Basic structure check — is the payload shaped like a project request?
        //    Checks: data required, data.type = 'project', data.project is array
        $importJsonValidator->validate($data);

        if ($importJsonValidator->hasErrors()) {
            $warning = $this->legacyAutoFixWarning($data, $projectSchemaValidator, $projectDefinitionValidator, $importProjectMappingValidator);

            return $this->warningErrorResponse('400', $this->parseKeyedErrors($importJsonValidator->errors()), $warning);
        }

        // 3. JSON Schema validation — full structural gate
        //    Validates against public/schemas/project.schema.json
        //    Checks: ref patterns, input keys, possible_answers limits,
        //    enums, string lengths, emoji/< > restrictions etc.
        if (!$projectSchemaValidator->validate($data)) {
            // Capture the raw violations BEFORE the warning check: legacyAutoFixWarning
            // re-runs the shared schema validator on the sanitised copy and would
            // otherwise clobber these.
            $schemaId = $projectSchemaValidator->schemaId();
            $violations = $projectSchemaValidator->violations();
            $warning = $this->legacyAutoFixWarning($data, $projectSchemaValidator, $projectDefinitionValidator, $importProjectMappingValidator);
            $errors = array_map(function (string $message) use ($schemaId) {
                return [
                    'schema' => $schemaId,
                    'title' => $message,
                    'source' => 'project-json-validator',
                ];
            }, $violations);

            return $this->warningErrorResponse('400', $errors, $warning);
        }

        $name = data_get($data, 'data.project.name', 'Imported Project');
        $projectDefinitionData = $data['data'];

        // We are validating, not importing: keep the payload's own project ref
        // intact and echo it back, rather than generating and assigning a new one.
        $projectRef = data_get($projectDefinitionData, 'project.ref', '');

        try {
            $projectDTO->validateProjectDefinitionAndMappings(
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
            $warning = $this->legacyAutoFixWarning($data, $projectSchemaValidator, $projectDefinitionValidator, $importProjectMappingValidator);

            return $this->warningErrorResponse('400', $this->parseKeyedErrors($errors), $warning);
        }

        return Response::apiSchemaSuccess(
            $projectRef,
            $name,
            $projectSchemaValidator->schemaId()
        );
    }

    /**
     * If the raw payload fails validation solely because of legacy issues that
     * are automatically fixed during import (see ProjectDTO::sanitiseProjectDefinitionForExport),
     * return a warning so clients know the payload would import successfully.
     * Returns null when the failure is not fully auto-fixable.
     */
    private function legacyAutoFixWarning(
        array $data,
        ProjectSchemaValidator $projectSchemaValidator,
        ProjectDefinitionValidator $projectDefinitionValidator,
        ImportProjectMappingValidator $importProjectMappingValidator
    ): ?string {
        // Auto-fixable issues only ever live inside data.project; if the basic
        // structure is missing, this is a genuine error with nothing to fix.
        $project = data_get($data, 'data.project');
        if (!is_array($project)) {
            return null;
        }

        // Build a sanitised copy of the definition — mirrors what the import path applies.
        $sanitised = $data;
        if (isset($sanitised['data'])) {
            $sanitised['data'] = ProjectDTO::sanitiseProjectDefinitionForExport($sanitised['data']);
        }

        // Schema gate on the sanitised copy.
        $schemaValid = $projectSchemaValidator->validate($sanitised);
        if (!$schemaValid) {
            return null;
        }

        // Definition + mapping gate on the sanitised copy, using a fresh DTO so we do not
        // mutate the validated one passed in by the container.
        $sanitisedDTO = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );

        try {
            $sanitisedDTO->validateProjectDefinitionAndMappings(
                $sanitised['data'],
                $projectDefinitionValidator,
                data_get($sanitised, 'meta.project_mapping'),
                $importProjectMappingValidator
            );
        } catch (Throwable) {
            return null;
        }

        // If the sanitised copy passes, every raw failure was auto-fixable.
        return config('epicollect.codes.ec5_409');
    }

    /**
     * Parse a keyed error structure (['source' => ['ec5_xxx', ...]]) into the
     * flat error objects used by the response, mirroring Response::apiErrorCode.
     */
    private function parseKeyedErrors(array $errors): array
    {
        $parsed = [];
        foreach ($errors as $key => $error) {
            if (!is_array($error)) {
                continue;
            }
            foreach ($error as $errorValue) {
                if ($key === 'question') {
                    $parsed[] = [
                        'code' => 'question',
                        'title' => $errorValue,
                        'source' => 'question',
                    ];
                } else {
                    $parsed[] = [
                        'code' => $errorValue,
                        'title' => str_contains($errorValue, 'ec5_')
                            ? config('epicollect.codes.' . $errorValue)
                            : $errorValue,
                        'source' => $key,
                    ];
                }
            }
        }
        return $parsed;
    }

    /**
     * Build a 400 error response, embedding the legacy-auto-fix warning (if any)
     * inside each error object. No macro is used so the warning travels through the
     * data argument rather than a dedicated macro parameter.
     */
    private function warningErrorResponse(string $httpStatusCode, array $errors, ?string $warning): JsonResponse
    {
        if ($warning !== null) {
            foreach ($errors as &$error) {
                $error['warning'] = $warning;
            }
            unset($error);
        }

        return new JsonResponse(
            ['errors' => $errors],
            (int) $httpStatusCode,
            ['Content-Type' => 'application/vnd.api+json; charset=utf-8'],
            0
        );
    }
}
