<?php

namespace ec5\Console\Commands;

use Illuminate\Console\Command;
use ec5\Services\Media\MediaCounterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemProjectStorageCommand extends Command
{
    protected $signature = 'system:project-storage {--project-ref= : Process only a specific project by ref}';
    protected $description = 'Compute total_bytes for all projects or a specific project';
    protected MediaCounterService $mediaCounterService;

    public function handle(): int
    {
        // Check if the total_bytes column exists
        if (!Schema::hasColumn('project_stats', 'total_bytes')) {
            $this->error('The total_bytes column does not exist in project_stats table.');
            $this->info('Please run the migration first: php artisan migrate');
            return 1;
        }

        $this->mediaCounterService = new MediaCounterService();
        $projectRef = $this->option('project-ref');

        if ($projectRef) {
            return $this->processSingleProject($projectRef);
        }

        return $this->processAllProjects();
    }

    private function processSingleProject($projectRef): int
    {
        $this->info("Processing project: $projectRef");

        $project = DB::table('project_stats')
            ->join('projects', 'projects.id', '=', 'project_stats.project_id')
            ->select('projects.id as project_id', 'project_stats.id as stats_id', 'projects.ref as project_ref')
            ->where('projects.ref', $projectRef)
            ->first();

        if (!$project) {
            $this->error("Project not found: $projectRef");
            return 1;
        }

        $mediaStats = $this->mediaCounterService->computeMediaMetrics($project->project_id, $project->project_ref);
        $this->updateMediaStorageStats($mediaStats, $project);

        return 0;
    }

    private function processAllProjects(): int
    {
        $totalProjects = DB::table('project_stats')->count();

        $this->info("Processing $totalProjects projects...");
        $bar = $this->output->createProgressBar($totalProjects);

        DB::table('project_stats')
            ->join('projects', 'projects.id', '=', 'project_stats.project_id')
            ->select('projects.id as project_id', 'project_stats.id as stats_id', 'projects.ref as project_ref')
            ->orderBy('project_stats.id')
            ->chunk(50, function ($rows) use ($bar) {
                foreach ($rows as $row) {
                    $mediaStats = $this->mediaCounterService->computeMediaMetrics($row->project_id, $row->project_ref);
                    $this->updateMediaStorageStats($mediaStats, $row);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info('Done!');
        return 0;
    }

    private function updateMediaStorageStats($mediaStats, mixed $row): void
    {
        $totalBytes = $mediaStats['sizes']['total_bytes'];
        $totalFiles = $mediaStats['counters']['photo'];
        $totalFiles += $mediaStats['counters']['audio'];
        $totalFiles += $mediaStats['counters']['video'];

        DB::table('project_stats')
            ->where('id', $row->stats_id)
            ->update([
                'total_bytes' => $totalBytes,
                'total_files' => $totalFiles,
                'photo_bytes' => $mediaStats['sizes']['photo_bytes'],
                'audio_bytes' => $mediaStats['sizes']['audio_bytes'],
                'video_bytes' => $mediaStats['sizes']['video_bytes'],
                'photo_files' => $mediaStats['counters']['photo'],
                'audio_files' => $mediaStats['counters']['audio'],
                'video_files' => $mediaStats['counters']['video'],
                'total_bytes_updated_at' => now()
            ]);
    }
}
