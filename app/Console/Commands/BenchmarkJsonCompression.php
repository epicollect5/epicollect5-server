<?php

namespace ec5\Console\Commands;

use ec5\Libraries\Utilities\Common;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Schema;
use Throwable;

/**
 * Command to benchmark JSON storage and performance across multiple MySQL/MariaDB
 * table configurations (different KEY_BLOCK_SIZE values) with COMPRESSED ROW_FORMAT.
 *
 * This command:
 * - Creates several tables (entries_json8, entries_json4, entries_json2, entries_json1)
 *   with the same schema but different KEY_BLOCK_SIZE settings.
 * - Prepares Faker instances for different locales and caches text, UUIDs and refs
 *   to speed up generation during the benchmark.
 * - Inserts a configurable number of rows in batches, using PDO prepared statements,
 *   while trying to be memory-safe (clearing params, running GC, committing
 *   at intervals).
 * - Measures insert time, memory usage, sample read latency and table on-disk size.
 *
 * Notes:
 * - Foreign key checks are disabled for the duration of the benchmark to avoid
 *   FK validation overhead during bulk inserts.
 * - The command assumes a MySQL-compatible database (SHOW TABLE STATUS, ALTER TABLE
 *   ROW_FORMAT=COMPRESSED, KEY_BLOCK_SIZE, etc.).
 */

class BenchmarkJsonCompression extends Command
{
    protected $signature = 'benchmark:json-compression 
        {--rows=10000000 : Number of rows to insert for each table}
        {--batch=10000 : Insert batch size}';

    protected $description = 'Benchmark storage and performance of entries_json4 vs entries_json2 (memory-safe, FK checks disabled)';

    private array $fakers = [];
    private array $textCache = [];
    private array $locales = ['en_US', 'fr_FR', 'de_DE', 'es_ES', 'ja_JP', 'zh_CN', 'ar_SA'];
    private array $uuidCache = [];
    private array $refCache = [];

    public function handle(): void
    {
        $rows = (int) $this->option('rows');
        $batch = (int) $this->option('batch');

        // One table per each block size plus uncompressed
        $tables = [
            'entries_json8' => 8,
            'entries_json4' => 4,
            'entries_json2' => 2,
            'entries_json1' => 1,
            'entries_json0' => null, // no compression
        ];

        // Prepare tables
        foreach ($tables as $table => $blockSize) {
            $this->info("▶ Preparing table: $table" . ($blockSize ? " (KEY_BLOCK_SIZE=$blockSize)" : " (uncompressed)"));
            Schema::dropIfExists($table);

            Schema::create($table, function (Blueprint $tableBlueprint) {
                $tableBlueprint->integer('entry_id')->primary();
                $tableBlueprint->json('entry_data')->nullable();
                $tableBlueprint->json('geo_json_data')->nullable();
                $tableBlueprint->foreign('entry_id')
                    ->references('id')
                    ->on(config('epicollect.tables.entries'))
                    ->onDelete('cascade');
            });

            if ($blockSize) {
                DB::statement("ALTER TABLE `$table` ROW_FORMAT=COMPRESSED, KEY_BLOCK_SIZE=$blockSize");
            }

            $this->info("   Table `$table` created" . ($blockSize ? " with KEY_BLOCK_SIZE=$blockSize." : " (uncompressed)."));
        }

        // Initialize Faker
        $this->info("Initializing Faker instances for multiple locales...");
        foreach ($this->locales as $locale) {
            $this->fakers[$locale] = Factory::create($locale);
        }

        // Pre-generate text cache for performance
        $this->info("Generating text cache for performance...");
        foreach ($this->locales as $locale) {
            $this->textCache[$locale] = [];
            for ($i = 0; $i < 100; $i++) {
                $text = $this->fakers[$locale]->text(rand(50, 250));
                $this->textCache[$locale][] = $text;
            }
        }

        // Pre-generate UUIDs and refs
        $this->info("Pre-generating UUIDs and refs...");
        for ($i = 0; $i < 1000; $i++) {
            $this->uuidCache[] = (string) Str::uuid();
        }
        for ($i = 1; $i <= 1000; $i++) {
            $this->refCache[] = "ref_$i";
        }

        // Disable FK checks
        DB::statement('SET foreign_key_checks = 0');
        try {

            // Metrics storage
            $metrics = [];

            // Benchmark each table
            foreach ($tables as $table => $blockSize) {
                $this->info("▶ Testing table: $table");
                DB::statement("TRUNCATE TABLE $table");

                $initialMemory = memory_get_usage(true);
                $peakMemory = $initialMemory;

                $this->info("   Inserting $rows rows in batches of $batch...");
                $this->info("   Initial memory: " . Common::formatBytes($initialMemory));

                $pdo = DB::connection()->getPdo();
                $startInsert = microtime(true);
                $inserted = 0;

                $placeholders = implode(',', array_fill(0, $batch, '(?, ?, ?)'));
                $insertSql = "INSERT INTO `$table` (entry_id, entry_data, geo_json_data) VALUES $placeholders";

                $transactionSize = max(5000, $batch * 2);
                $pdo->beginTransaction();

                while ($inserted < $rows) {
                    $chunkSize = min($batch, $rows - $inserted);
                    $params = [];

                    for ($i = 0; $i < $chunkSize; $i++) {
                        $id = $inserted + $i + 1;
                        $params[] = $id;
                        $params[] = $this->generateEntryData();
                        $params[] = $this->generateEntryGeoJson();
                    }

                    if ($chunkSize === $batch) {
                        $stmt = $pdo->prepare($insertSql);
                    } else {
                        $partialPlaceholders = implode(',', array_fill(0, $chunkSize, '(?, ?, ?)'));
                        $stmt = $pdo->prepare("INSERT INTO `$table` (entry_id, entry_data, geo_json_data) VALUES $partialPlaceholders");
                    }

                    $stmt->execute($params);
                    $stmt = null;

                    $inserted += $chunkSize;

                    if ($inserted % $transactionSize === 0) {
                        $pdo->commit();
                        $pdo->beginTransaction();
                    }

                    $currentMemory = memory_get_usage(true);
                    $peakMemory = max($peakMemory, $currentMemory);

                    unset($params);
                    gc_collect_cycles();

                    if ($inserted % (10 * $batch) === 0) {
                        $elapsed = round(microtime(true) - $startInsert, 1);
                        $memoryAfterGC = memory_get_usage(true);
                        $memoryUsed = $memoryAfterGC - $initialMemory;

                        $this->info(sprintf(
                            "   ... inserted %s rows (%ss) | Mem: %s (peak: %s)",
                            number_format($inserted),
                            $elapsed,
                            Common::formatBytes($memoryUsed),
                            Common::formatBytes($peakMemory - $initialMemory)
                        ));
                    }
                }

                $pdo->commit();
                $insertTime = round(microtime(true) - $startInsert, 2);

                $finalMemory = memory_get_usage(true);
                $totalPeakMemory = memory_get_peak_usage(true);

                $this->info("   ✅ Insert completed in {$insertTime}s");
                $this->info("   💾 Final memory: " . Common::formatBytes($finalMemory - $initialMemory));
                $this->info("   📊 Peak memory: " . Common::formatBytes($totalPeakMemory));

                // Measure read performance
                $this->info("   Measuring 100 random reads...");
                $startRead = microtime(true);
                for ($k = 0; $k < 100; $k++) {
                    $id = rand(1, $rows);
                    DB::table($table)->where('entry_id', $id)->first();
                }
                $readTime = round(microtime(true) - $startRead, 3);
                $this->info("   ✅ 100 random reads in {$readTime}s");

                // Table size
                DB::statement("ANALYZE TABLE `$table`");
                $status = DB::selectOne("SHOW TABLE STATUS LIKE '$table'");
                $sizeMb = round(($status->Data_length + $status->Index_length) / 1024 / 1024, 2);
                $this->info("   📦 Table size: $sizeMb MB");
                $this->line(str_repeat('-', 50));

                // Store metrics
                $metrics[$table] = [
                    'size' => $sizeMb,
                    'insert_time' => $insertTime,
                    'read_time' => $readTime,
                ];
            }
        } catch (Throwable $e) {
            $this->error("Error: " . $e->getMessage());
        } finally {
            // Re-enable FK checks
            DB::statement('SET foreign_key_checks = 1');
        }


        // Re-enable FK checks
        DB::statement('SET foreign_key_checks = 1');

        // Print summary vs uncompressed
        $baseline = $metrics['entries_json0'] ?? null;
        if ($baseline) {
            $this->info("\n📊 Compression summary vs uncompressed table (entries_json0):");
            foreach ($metrics as $table => $m) {
                if ($table === 'entries_json0') {
                    continue;
                }

                // Calculate percentage difference
                $percent = fn ($a, $b) => $b ? round(($a - $b) / $b * 100, 2) : 0;
                // Get metrics avoiding divide by zero errors
                $sizePct   = $percent($baseline['size'] ?? 0, $m['size']);
                $insertPct = $percent($m['insert_time'] ?? 0, $baseline['insert_time']);
                $readPct   = $percent($m['read_time'] ?? 0, $baseline['read_time']);

                $this->info(sprintf(
                    "Table %s: size ↓ %s%%, insert Δ %s%%, read Δ %s%%",
                    $table,
                    $sizePct,
                    $insertPct,
                    $readPct
                ));
            }
        }

        $this->info("Benchmark completed ✅");

        // Clean up
        foreach ($tables as $table => $blockSize) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Generate the JSON-encoded entry_data for benchmark.
     */
    private function generateEntryData(): string
    {
        $rand = rand(1, 1000);
        $answerCount = rand(1, 500);

        // Pick a random locale for this entry
        $locale = $this->locales[array_rand($this->locales)];

        $answers = [];
        for ($i = 1; $i <= $answerCount; $i++) {
            $key = "question_{$rand}_$i";
            $answers[$key] = [
                'answer' => $this->generateAnswer($locale),
                'was_jumped' => false,
            ];
        }

        $entryData = [
            'id' => $this->uuidCache[array_rand($this->uuidCache)],
            'type' => 'entry',
            'entry' => [
                'title' => "Random title $rand",
                'answers' => $answers,
            ],
            'attributes' => [
                'form' => ['ref' => $this->refCache[array_rand($this->refCache)], 'type' => 'hierarchy'],
            ],
            'relationships' => [
                'branch' => ['data' => ['owner_input_ref' => $this->refCache[array_rand($this->refCache)], 'owner_entry_uuid' => $this->uuidCache[array_rand($this->uuidCache)]]],
                'parent' => ['data' => ['parent_form_ref' => $this->refCache[array_rand($this->refCache)], 'parent_entry_uuid' => $this->uuidCache[array_rand($this->uuidCache)]]],
            ],
        ];

        $json = json_encode($entryData, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            // Fallback to simple data if encoding fails
            $this->warn("JSON encoding failed: " . json_last_error_msg());
            return json_encode(['id' => (string) Str::uuid(), 'type' => 'entry', 'error' => 'encoding_failed']);
        }

        return $json;
    }


    private function generateEntryGeoJson(): string
    {
        $features = [];

        // Random number of features per entry, e.g., 1 to 10
        $featureCount = rand(1, 10);
        for ($k = 1; $k <= $featureCount; $k++) {
            $uuid = $this->uuidCache[array_rand($this->uuidCache)];
            $lat = rand(-90000000, 90000000) / 1e6;
            $lng = rand(-180000000, 180000000) / 1e6;
            $accuracy = rand(1, 100);

            // Random possible answers, e.g., 5 to 15 keys
            $possibleAnswers = [];
            $answerCount = rand(5, 15);
            for ($pa = 0; $pa < $answerCount; $pa++) {
                $possibleAnswers[$this->uuidCache[array_rand($this->uuidCache)]] = 1;
            }

            $features["feature_{$k}_$uuid"] = [
                'id' => $uuid,
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat],
                ],
                'properties' => [
                    'uuid' => $uuid,
                    'title' => "Random title $k",
                    'accuracy' => $accuracy,
                    'created_at' => now()->toDateString(),
                    'possible_answers' => $possibleAnswers,
                ],
            ];
        }

        return json_encode($features, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate a random answer using cached text for the given locale
     */
    private function generateAnswer(string $locale): string
    {
        $length = rand(50, 300);
        $text = $this->textCache[$locale][array_rand($this->textCache[$locale])];

        if (mb_strlen($text, 'UTF-8') < $length) {
            // Repeat text to reach desired length
            $text = str_repeat($text . ' ', (int)ceil($length / mb_strlen($text, 'UTF-8')));
        }

        return mb_substr($text, 0, $length, 'UTF-8');
    }
}
