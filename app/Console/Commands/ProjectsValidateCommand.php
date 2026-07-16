<?php

namespace ec5\Console\Commands;

use ec5\Models\Project\Project;
use ec5\Services\Project\ProjectValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProjectsValidateCommand extends Command
{
    private const string DISK = 'temp';

    protected $signature = 'projects:validate
        {--slug= : Validate only the project with this slug}
        {--limit= : Cap the number of projects processed (debugging)}
        {--offset= : Skip the first N projects (resumability)}';

    protected $description = 'Validate every project\'s default mapping and definition against the import-validation rules';

    public function __construct(
        private ProjectValidationService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $slug = $this->option('slug');

        if ($slug !== null && $slug !== '') {
            return $this->validateSingle($slug);
        }

        return $this->validateAll();
    }

    private function validateSingle(string $slug): int
    {
        $this->service->resetFailures();

        $this->info("Validating project: $slug");

        $result = $this->service->validateProject($slug);

        if ($result['status'] === 'pass') {
            $this->info("✓ {$result['name']} ({$result['slug']}) passed validation.");
            return 0;
        }

        if ($result['status'] === 'skipped') {
            $this->info("- {$result['name']} ({$result['slug']}) skipped: no questions yet.");
            return 0;
        }

        $this->error("✗ {$result['name']} ({$result['slug']}) failed validation:");
        $this->line(implode(' | ', $this->flattenErrors($result['errors'])));

        return 1;
    }

    private function validateAll(): int
    {
        $this->service->resetFailures();

        $offset = (int) $this->option('offset');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $query = Project::where('status', '<>', 'archived')->orderBy('id');
        if ($offset > 0) {
            $query->skip($offset);
        }
        if ($limit !== null) {
            $query->take($limit);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No projects to validate.');
            return 0;
        }

        $this->info("Validating $total project(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $failures = 0;
        $skipped = 0;
        $failureRows = [];

        $query->chunk(200, function ($projects) use ($bar, &$failures, &$skipped, &$failureRows): void {
            foreach ($projects as $project) {
                $result = $this->service->validateProject($project->slug);
                if ($result['status'] === 'fail') {
                    $failures++;
                    $failureRows[] = [
                        $result['name'],
                        implode(' | ', $this->flattenErrors($result['errors']))
                    ];
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Done. $total project(s) validated, $failures failure(s), $skipped skipped (no questions).");

        if ($failures > 0) {
            $csvPath = $this->service->failuresCsvPath();
            if ($csvPath) {
                $this->newLine();
                $this->table(
                    ['Name', 'Errors'],
                    $failureRows
                );
                $this->info('Full report (CSV): ' . Storage::disk(self::DISK)->path($csvPath));
            }
        }

        return 0;
    }

    private function flattenErrors(array $errors): array
    {
        $flat = [];
        array_walk_recursive($errors, function ($value) use (&$flat): void {
            $flat[] = $this->resolveErrorCode($value);
        });

        return $flat;
    }

    /**
     * Resolve an ec5_* error code to its human-readable label; leave non-code
     * values (e.g. JSON Schema violation strings) unchanged. Mirrors the service.
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
