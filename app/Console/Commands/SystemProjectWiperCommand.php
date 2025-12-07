<?php

namespace ec5\Console\Commands;

use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Traits\Project\ProjectWiper;
use Illuminate\Console\Command;
use Log;
use Storage;
use Throwable;

class SystemProjectWiperCommand extends Command
{
    use ProjectWiper;

    protected $signature = 'system:project-wipe 
    {--project-id= : Process only a specific project by ID}
    {--dry-run : List what would be deleted without deleting}';

    protected $description = 'Permanently wipe an archived project, all its entries, and media (chunked). Irreversible.';

    public function handle(): int
    {
        $projectIdRaw = $this->option('project-id');
        $projectId = (int) $projectIdRaw;

        // Explicit check for a missing ID
        if (!$projectIdRaw || $projectId === 0) {
            $this->error("The --project-id option is required and must be a valid ID.");
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $project = Project::where('id', $projectId)
            ->first();

        if (!$project) {
            $this->error("Project not found with ID $projectId");
            return self::FAILURE;
        }

        if ($project->status !== 'archived') {
            $this->error("Project must be archived before erasing.");
            return self::FAILURE;
        }

        // Get original name from JSON definition
        $originalName = $this->getOriginalProjectName($projectId) ?? '(unknown original name)';

        $this->warn("You are about to PERMANENTLY ERASE this project:");
        $this->line("  • ID:    $project->id");
        $this->line("  • Ref (media folders):   $project->ref");
        $this->line("  • Slug (new):  $project->slug");
        $this->line("  • Original Name:  $originalName");

        $typedName = $this->ask("To confirm, type the project’s original name: $originalName");
        if ($typedName !== $originalName) {
            $this->error("Confirmation name does not match. Aborting.");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("\n[DRY-RUN] Listing what would be deleted...");

            // Media
            $mediaDisks = config('epicollect.media.entries_deletable');
            foreach ($mediaDisks as $disk) {
                $files = Storage::disk($disk)->allFiles($project->ref);
                $this->line("Disk $disk/$project->ref: " . count($files) . " files would be deleted");
            }

            // Entries
            $entriesCount = Entry::where('project_id', $projectId)->count();
            $branchEntriesCount = BranchEntry::where('project_id', $projectId)->count();
            $this->line("Entries: $entriesCount rows would be deleted, and related $branchEntriesCount branch entries.");

            $this->info("\nDry-run complete. No data was deleted.");
            return self::SUCCESS;
        }

        $this->info("Starting erase process...");

        // Create progress bar
        $progress = $this->output->createProgressBar();
        $progress->setFormat(" %message%\n%bar% %percent%%");
        $progress->setMessage("Preparing...");

        try {
            $this->eraseProject($projectId, $project->slug, $progress);
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            $this->error("Error erasing project: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine(2);
        $this->info("Project erased successfully.");

        return self::SUCCESS;
    }
}
