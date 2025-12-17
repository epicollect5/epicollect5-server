<?php

namespace ec5\Console\Commands;

use Carbon\CarbonInterval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ec5\Libraries\Utilities\Common;
use Throwable;

class SystemMigrateEntriesJson extends Command
{
    protected $signature = 'system:migrate-entries-json 
        {--project= : Project id to filter by}
        {--year= : Year to filter entries by created_at}
        {--limit=1000 : Number of entries to migrate per run}
        {--branch : Use branch_entries tables instead of entries}
        {--dry-run : Simulate migration without making changes}';

    protected $description = 'Migrate entry_data and geo_json_data to JSON tables with integrity checks and memory tracking';

    private const int CHUNK_SIZE = 100;

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        $isBranch = $this->option('branch');
        $projectId = $this->option('project');
        $year = $this->option('year');
        $limit = (int) $this->option('limit');
        $isDryRun = $this->option('dry-run');
        $projectName = '';

        $srcTable = $isBranch ? 'branch_entries' : 'entries';
        $dstTable = $isBranch ? 'branch_entries_json' : 'entries_json';

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made to the database');
        }

        // Count total entries to migrate (skip if filtering by year as it's slow)
        if (!$year) {
            $countQuery = DB::table($srcTable)->whereNotNull('entry_data');

            if ($projectId) {
                $countQuery->where('project_id', $projectId);
            }

            $totalCount = $countQuery->count();
            $entriesToProcess = min($totalCount, $limit);

            if ($totalCount === 0) {
                $this->info('✅ No entries found to migrate.');
                return 0;
            }

            $this->info("Migrating $entriesToProcess entries from $srcTable → $dstTable...");
        } else {
            $this->info("Migrating entries from $srcTable → $dstTable (limit: $limit)...");
            $this->info("   ⚠️  Skipping count due to year filter (slow on large tables)");
        }

        $this->info(sprintf(
            "   Filters: project=%s | year=%s | limit=%d | chunk=%d",
            $projectId ?: '—',
            $year ?: '—',
            $limit,
            self::CHUNK_SIZE
        ));

        // --- Memory tracking start ---
        $initialMemory = memory_get_usage(true);
        $this->info("   💾 Initial memory: " . Common::formatBytes($initialMemory));
        $startTime = microtime(true);
        // -----------------------------

        $migrated = 0;
        $wouldMigrate = 0;
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        // Build base query
        $query = DB::table($srcTable)
            ->whereNotNull('entry_data')
            ->orderBy('id');

        if ($projectId) {
            $projectName = DB::table('projects')->where('id', $projectId)->value('name');
            $query->where('project_id', $projectId);
        }
        if ($year) {
            $query->where('created_at', '>=', "$year-01-01 00:00:00")
                ->where('created_at', '<', ($year + 1) . "-01-01 00:00:00");
        }

        // Pre-fetch all entry IDs to process
        $this->info("📋 Fetching entry IDs...");
        $entryIds = $query->limit($limit)->pluck('id');
        $this->info("   Found " . $entryIds->count() . " entries to process");

        // Progress bar setup (or chunk counter for year filtering)
        $useProgressBar = !$year;
        $bar = null;

        if ($useProgressBar) {
            $bar = $this->output->createProgressBar($entryIds->count());
            $bar->setFormat('   🧩 [%bar%] %current%/%max% | %percent:3s%% | %elapsed:6s% | Mem: %memory%');
        }

        // Process in chunks
        $chunkNumber = 0;
        $totalChunks = ceil($entryIds->count() / self::CHUNK_SIZE);

        foreach ($entryIds->chunk(self::CHUNK_SIZE) as $chunkIds) {
            $chunkNumber++;

            // Log chunk progress if not using progress bar
            if (!$useProgressBar) {
                $chunkStart = ($chunkNumber - 1) * self::CHUNK_SIZE + 1;
                $chunkEnd = min($chunkNumber * self::CHUNK_SIZE, $entryIds->count());
                $currentMemory = memory_get_usage(true);
                $elapsed = round(microtime(true) - $startTime, 1);

                // Calculate interval
                $elapsedInterval = \Carbon\CarbonInterval::seconds($elapsed)
                    ->cascade()
                    ->forHumans(['short' => true]); // 'short' gives you '1m 10s' instead of '1 minute 10 seconds'

                $output = sprintf(
                    "\r   🧩 Chunk %5d/%-5d | Entries %7d-%-7d | Migrated: %6d | Elapsed: %12s | Mem: %10s\x1B[K",
                    $chunkNumber,
                    $totalChunks,
                    $chunkStart,
                    $chunkEnd,
                    $migrated,
                    str_pad($elapsedInterval, 12, ' ', STR_PAD_LEFT), // Lock to 12 chars
                    Common::formatBytes($currentMemory)
                );

                $this->output->write($output, false);
            }

            foreach ($chunkIds as $entryId) {
                if (!$isDryRun) {
                    DB::beginTransaction();
                }

                try {
                    // Row-level lock
                    $locked = DB::table($srcTable)
                        ->where('id', $entryId)
                        ->when(!$isDryRun, fn ($q) => $q->lockForUpdate())
                        ->first();

                    if (!$locked || is_null($locked->entry_data)) {
                        if (!$isDryRun) {
                            DB::rollBack();
                        }
                        $bar->advance();
                        $skipped++;
                        $processed++;
                        continue;
                    }

                    $payload = [
                        'entry_id' => $locked->id,
                        'project_id' => $locked->project_id,
                        'entry_data' => $locked->entry_data,
                        'geo_json_data' => $locked->geo_json_data
                    ];

                    if ($isDryRun) {
                        // In dry run, just check if the entry exists in destination
                        $existsInDst = DB::table($dstTable)->where('entry_id', $locked->id)->exists();

                        // Simulate checksum validation
                        $srcHash = md5(($locked->entry_data) . ($locked->geo_json_data ?? ''));

                        if ($existsInDst) {
                            $dstRow = DB::table($dstTable)->where('entry_id', $locked->id)->first();
                            $dstHash = md5(($dstRow->entry_data ?? '') . ($dstRow->geo_json_data ?? ''));

                            if ($srcHash === $dstHash) {
                                $wouldMigrate++;
                            }
                        } else {
                            $wouldMigrate++;
                        }
                    } else {
                        DB::table($dstTable)->updateOrInsert(
                            ['entry_id' => $locked->id],
                            $payload
                        );

                        if ($this->checkEntryMd5($locked, $dstTable)) {
                            DB::table($srcTable)
                                ->where('id', $locked->id)
                                ->update(['entry_data' => null, 'geo_json_data' => null]);
                            DB::commit();
                            $migrated++;
                        } else {
                            DB::rollBack();
                            $failed++;
                            $this->warn("❌ MD5 mismatch for entry ID $locked->id");
                        }
                    }

                } catch (Throwable $e) {
                    if (!$isDryRun) {
                        DB::rollBack();
                    }
                    $this->error("⚠️ Error on entry ID $entryId: {$e->getMessage()}");
                }

                $bar?->advance();
                $processed++;

                // Update memory display for progress bar
                if ($useProgressBar) {
                    $currentMemory = memory_get_usage(true);
                    $bar->setMessage(Common::formatBytes($currentMemory), 'memory');
                }
            }

            // Memory cleanup after each chunk
            gc_collect_cycles();
        }

        $bar?->finish();
        if (!$useProgressBar) {
            $this->newLine(); // Clean line after chunk updates
        }
        $this->newLine(2);

        // --- Final stats ---
        $elapsedTotal = round(microtime(true) - $startTime, 2);
        $finalMemory = memory_get_usage(true);
        $peakMemoryFinal = memory_get_peak_usage(true);
        $memoryDelta = $finalMemory - $initialMemory;

        $this->info("✅ " . ($isDryRun ? "Dry run" : "Migration") . " complete.");

        if ($isDryRun) {
            $this->info("   🔍 Would migrate: $wouldMigrate/$processed entries");
            $this->info("   🗑 Skipped: $skipped/$processed entries");
            $this->info("   🚫 Failed: $failed/$processed entries");
            $this->warn("   ⚠️  No changes were made to the database");
        } else {
            $this->info("   🧾 Migrated entries: $migrated/$processed");
            $this->info("   🗑 Skipped: $skipped/$processed entries");
            $this->info("   🚫 Failed: $failed/$processed entries");
        }

        if ($projectName) {
            $this->info("   📁 Project: $projectName ( id -> $projectId)");
        }

        $this->comment("   ⏱ Total time: " . CarbonInterval::seconds($elapsedTotal)->cascade()->forHumans());
        $this->comment("   ⏱ Avg per entry: " . round($elapsedTotal / max($processed, 1) * 1000, 2) . "ms");
        $this->comment("   💾 Memory delta: " . Common::formatBytes($memoryDelta));
        $this->comment("   📈 Peak memory: " . Common::formatBytes($peakMemoryFinal));

        return 0;
    }

    public function checkEntryMd5(object $locked, string $dstTable): bool
    {
        // MD5 checksum comparison
        $srcHash = md5(($locked->entry_data) . ($locked->geo_json_data ?? ''));
        $dstRow = DB::table($dstTable)->where('entry_id', $locked->id)->first();
        $dstHash = md5(($dstRow->entry_data ?? '') . ($dstRow->geo_json_data ?? ''));

        return $srcHash === $dstHash;
    }
}
