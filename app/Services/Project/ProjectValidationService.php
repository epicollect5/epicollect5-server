<?php

namespace ec5\Services\Project;

use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Http\Validation\Project\Mapping\RuleImportProjectMapping as ImportProjectMappingValidator;
use ec5\Http\Validation\Project\RuleImportJson as ImportJsonValidator;
use ec5\Http\Validation\Project\RuleProjectDefinition as ProjectDefinitionValidator;
use ec5\Http\Validation\Schemas\ProjectSchemaValidator;
use ec5\Models\Project\Project;
use ec5\Services\Mapping\ProjectMappingService;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProjectValidationService
{
    private const string DISK = 'temp';
    private const string DIR = 'validate-projects';

    public function __construct(
        private ImportJsonValidator $importJsonValidator,
        private ProjectSchemaValidator $projectSchemaValidator,
        private ProjectDefinitionValidator $projectDefinitionValidator,
        private ImportProjectMappingValidator $importProjectMappingValidator
    ) {
    }

    /**
     * Validate a single project's default mapping and definition using the same
     * chain as POST api/import/project/validate. On failure, append a row to the
     * per-admin failures CSV.
     *
     * @return array{slug:string, name:string, ref:string, status:string, schema_id:string, errors:array}
     */
    public function validateProject(string $slug): array
    {
        $projectRow = Project::where('slug', $slug)
            ->where('status', '<>', 'archived')
            ->first();

        if (!$projectRow) {
            return [
                'slug' => $slug,
                'name' => '',
                'ref' => '',
                'status' => 'fail',
                'schema_id' => '',
                'errors' => ['project not found']
            ];
        }

        // Source DTO — initialised from the database, used ONLY to extract the project's
        // definition and default mapping so we can build the validation payload. It is never
        // passed to the validators, because validating against a DTO pre-loaded with the
        // project's real mappings causes spurious collisions (e.g. ec5_228 "mapping name
        // already exists" when a custom default mapping name like "Test" matches itself).
        $sourceDTO = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $sourceDTO->initAllDTOs(Project::findBySlug($slug));

        // A project with no definition (e.g. missing project_structures row) cannot be validated.
        if (empty($sourceDTO->getProjectDefinition()->getData()['project'] ?? [])) {
            return $this->fail(
                $sourceDTO,
                $projectRow,
                ['project_definition' => ['ec5_67']]
            );
        }

        $definition = $sourceDTO->getSanitisedProjectDefinition();

        // Skip projects that have no questions yet — a project whose forms all have
        // zero inputs is not "broken", it just has nothing to validate. Reporting these
        // as failures would flood the CSV with schema "at least 1 item" noise.
        if ($this->hasNoQuestions($definition)) {
            return [
                'slug' => $sourceDTO->slug,
                'name' => $sourceDTO->name,
                'ref' => $sourceDTO->ref,
                'status' => 'skipped',
                'schema_id' => $this->projectSchemaValidator->schemaId(),
                'errors' => ['no_questions' => ['ec5_68']]
            ];
        }

        // Default mapping only — mimics project imports/exports.
        $mappingDTO = $sourceDTO->getProjectMapping();
        $defaultIndex = $mappingDTO->getDefaultMapIndex();
        $mapping = $mappingDTO->getData()[$defaultIndex] ?? null;

        $payload = [
            'data' => $definition,
            'meta' => [
                'project_mapping' => $mapping !== null ? [$mapping] : []
            ]
        ];

        // 1. Basic structure check (mirrors validateImport).
        $this->importJsonValidator->validate($payload);
        if ($this->importJsonValidator->hasErrors()) {
            return $this->fail($sourceDTO, $projectRow, $this->importJsonValidator->errors());
        }

        // 2. JSON Schema validation (mirrors validateImport).
        if (!$this->projectSchemaValidator->validate($payload)) {
            return $this->fail($sourceDTO, $projectRow, $this->projectSchemaValidator->violations());
        }

        // 3. Definition + mapping validation (mirrors validateImport).
        // Use a FRESH, empty DTO here — exactly like the one injected into the
        // validateImport endpoint. This ensures the mapping validator's additionalChecks
        // only sees the EC5 AUTO mapping it builds internally, never the project's real
        // mappings, so a custom default mapping name does not collide with itself.
        $validateDTO = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );

        try {
            $validateDTO->validateProjectDefinitionAndMappings(
                $payload['data'],
                $this->projectDefinitionValidator,
                $payload['meta']['project_mapping'],
                $this->importProjectMappingValidator
            );
        } catch (Throwable $e) {
            $errors = $this->importProjectMappingValidator->errors();
            if (empty($errors)) {
                $errors = $this->projectDefinitionValidator->errors();
            }
            if (empty($errors)) {
                $errors = ['validation' => ['ec5_39']];
            }
            return $this->fail($sourceDTO, $projectRow, $errors);
        }

        return [
            'slug' => $sourceDTO->slug,
            'name' => $sourceDTO->name,
            'ref' => $sourceDTO->ref,
            'status' => 'pass',
            'schema_id' => $this->projectSchemaValidator->schemaId(),
            'errors' => []
        ];
    }

    /**
     * @param array $errors
     * @return array{slug:string, name:string, ref:string, status:string, schema_id:string, errors:array}
     */
    private function fail(ProjectDTO $projectDTO, object $projectRow, array $errors): array
    {
        $result = [
            'slug' => $projectDTO->slug,
            'name' => $projectDTO->name,
            'ref' => $projectDTO->ref,
            'url' => rtrim(config('app.url'), '/') . '/project/' . $projectDTO->slug,
            'status' => 'fail',
            'schema_id' => $this->projectSchemaValidator->schemaId(),
            'errors' => $errors
        ];

        $this->appendFailure($result);

        return $result;
    }

    /**
     * Append a failed project row to the failures CSV.
     */
    private function appendFailure(array $row): void
    {
        $csvPath = self::DIR . '/failures.csv';
        $storage = Storage::disk(self::DISK);

        if (!$storage->exists(self::DIR)) {
            $storage->makeDirectory(self::DIR);
        }

        $exists = $storage->exists($csvPath);

        $handle = fopen($storage->path($csvPath), 'a');
        if ($handle === false) {
            return;
        }

        if (!$exists) {
            fputcsv($handle, ['slug', 'name', 'project_ref', 'url', 'errors']);
        }

        $errors = $row['errors'] ?? [];
        $flat = [];
        array_walk_recursive($errors, function ($value) use (&$flat): void {
            $flat[] = $this->resolveErrorCode($value);
        });

        fputcsv($handle, [
            $row['slug'] ?? '',
            $row['name'] ?? '',
            $row['ref'] ?? '',
            $row['url'] ?? '',
            implode(' | ', $flat)
        ]);
        fclose($handle);
    }

    /**
     * Path (relative to disk) of the failures CSV, if it exists.
     */
    public function failuresCsvPath(): ?string
    {
        $csvPath = self::DIR . '/failures.csv';
        if (!Storage::disk(self::DISK)->exists($csvPath)) {
            return null;
        }

        return $csvPath;
    }

    /**
     * Remove any existing failures CSV so a fresh run starts clean.
     */
    public function resetFailures(): void
    {
        $csvPath = self::DIR . '/failures.csv';
        if (Storage::disk(self::DISK)->exists($csvPath)) {
            Storage::disk(self::DISK)->delete($csvPath);
        }
    }

    /**
     * A project "has no questions" when every form has an empty inputs array.
     */
    private function hasNoQuestions(array $definition): bool
    {
        $forms = $definition['project']['forms'] ?? [];
        if (empty($forms)) {
            return true;
        }

        foreach ($forms as $form) {
            $inputs = $form['inputs'] ?? [];
            if (!empty($inputs)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve an ec5_* error code to its human-readable label. Values that are not
     * ec5_* codes (e.g. JSON Schema violation strings) are returned unchanged.
     */
    private function resolveErrorCode(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^ec5_\d+$/', $value)) {
            return (string) $value;
        }

        $label = config("epicollect.codes.$value");
        if ($label === null) {
            return $value;
        }

        return $value . ': ' . $label;
    }
}
