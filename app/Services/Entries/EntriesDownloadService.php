<?php

namespace ec5\Services\Entries;

use DB;
use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Services\Mapping\DataMappingService;
use File;
use Illuminate\Database\Query\Builder;
use Log;
use RuntimeException;
use Storage;
use Throwable;
use ZipArchive;

class EntriesDownloadService
{
    protected ProjectDTO $project;
    protected DataMappingService $dataMappingService;
    protected array $errors = [];
    private float $totalDuration = 0;
    private int $totalEntries = 0;

    /**
     * Constructs a new EntriesDownloadService instance.
     *
     * Injects the data mapping service dependency used for processing project entries.
     */
    public function __construct(DataMappingService $dataMappingService)
    {
        $this->dataMappingService = $dataMappingService;
    }

    /**
     * Generates archive files for project entries and compiles them into a ZIP archive.
     *
     * This function resets performance counters and clears the target directory before processing each form
     * and its related branch entries defined in the project. It retrieves entry data for both forms and branches,
     * logs the duration of each query, writes the data to files in the specified format (CSV or JSON), and then
     * archives all the generated files into a ZIP. The process halts and returns false if any file writing or
     * archiving operation fails.
     *
     * @param ProjectDTO $project Project data used for generating the archive.
     * @param string $projectDir Directory path where archive files are temporarily stored.
     * @param array $params Array of parameters including:
     *   - 'format': Desired output format (e.g., 'csv' or 'json').
     *   - 'map_index': Index used for data mapping.
     *
     * @return bool True if the archive is successfully created; false otherwise.
     */
    public function createArchive(ProjectDTO $project, string $projectDir, array $params): bool
    {
        $this->totalDuration = 0;
        $this->totalEntries = 0;
        $this->project = $project;
        // Delete all existing files for this user
        Storage::deleteDirectory($projectDir);

        $format = $params['format'];
        $mapIndex = $params['map_index'];

        $forms = $project->getProjectDefinition()->getData()['project']['forms'];
        $formCount = 1;
        $branchCount = 1;

        Log::info("Started archive creation for project", [
            'project' => $this->project->name,
        ]);

        foreach ($forms as $form) {

            // Set the form ref into the params
            $params['form_ref'] = $form['ref'];
            // Let's start with forms first
            $prefix = config('epicollect.strings.form') . '-' . $formCount;
            $fileName = Common::generateFilename($prefix, $form['slug']);
            // Set the mapping
            $this->dataMappingService->init($this->project, $format, config('epicollect.strings.form'), $form['ref'], null, $mapIndex);

            $columns = ['id', 'title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at'];

            // Log the start of the query for form entries
            $startTime = microtime(true);
            Log::info("Starting query for form entries: {$form['ref']}");
            // Get the query for these entries
            $query = (new Entry())->getEntriesByFormForArchive($this->project->getId(), $params, $columns);
            // Log the end of the query and calculate duration
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 4);
            Log::info("Query for form entries completed: {$form['ref']}", [
                'duration_seconds' => $duration,
            ]);

            // Write to file
            if (!$this->writeToFile($query, $projectDir, $fileName, $format)) {
                return false;
            }
            // Get all branches for this form
            $branches = [];
            $inputs = $form['inputs'];
            foreach ($inputs as $input) {
                if ($input['type'] === 'branch') {
                    $branches[] = $input;
                }
            }

            foreach ($branches as $branch) {
                // Set the branch ref into the options
                $params['branch_ref'] = $branch['ref'];
                $prefix = config('epicollect.strings.branch') . '-' . $branchCount;
                $fileName = Common::generateFilename($prefix, $branch['question']);
                // Set the mapping
                $this->dataMappingService->init(
                    $this->project,
                    $format,
                    config('epicollect.strings.branch'),
                    $form['ref'],
                    $branch['ref'],
                    $mapIndex
                );

                $columns = ['id', 'uuid', 'title', 'entry_data', 'user_id', 'uploaded_at'];

                // Log the start of the query for branch entries
                $startTime = microtime(true);
                Log::info("Starting query for branch entries: {$branch['ref']}");
                // Get the query for these branch entries
                $query = (new BranchEntry())->getBranchEntriesByBranchRefForArchive(
                    $this->project->getId(),
                    $params,
                    $columns
                );

                // Log the end of the query and calculate duration
                $endTime = microtime(true);
                $duration = round($endTime - $startTime, 4);
                Log::info("Query for branch entries completed: {$branch['ref']}", [
                    'duration_seconds' => $duration,
                ]);

                // Write to file
                if (!$this->writeToFile($query, $projectDir, $fileName, $format)) {
                    return false;
                }
                $branchCount++;
            }
            $formCount++;
        }
        try {
            Log::info("All files completed SEQUENTIAL", [
                'project' => $this->project->name,
                'total_duration' => round($this->totalDuration / 60, 2) . ' minutes'
            ]);
            $this->buildZipArchive($projectDir, $project->slug, $format);
        } catch (Throwable $e) {
            Log::error('buildZipArchive failed', ['exception' => $e->getMessage()]);
            return false;
        }
        return true;
    }

    private function buildZipArchive($projectDir, $projectSlug, $format): void
    {
        $zip = new ZipArchive();
        $zipFileName = $projectSlug . '-' . $format . '.zip';
        $zip->open($projectDir . '/' . $zipFileName, ZipArchive::CREATE);
        $toDeleteLater = [];

        foreach (glob($projectDir . '/*.' . $format) as $file) {
            $zip->addFile($file, basename($file));
            //save file names for deletion
            $toDeleteLater[] = $file;
        }
        $zip->close();

        //delete csv files as they got copied into the zip already
        foreach ($toDeleteLater as $file) {
            unlink($file);
        }
    }

    /**
     * Writes the results of a query to a file in the specified format.
     *
     * This method ensures the target directory exists (creating it if necessary) and builds the output file path
     * by appending the file name and format. It then delegates the file writing to either a CSV or JSON writer based
     * on the provided format.
     *
     * @param Builder $query The query builder instance for retrieving the data entries.
     * @param string $projectDir The directory where the output file will be stored.
     * @param string $fileName The base name for the output file (without extension).
     * @param string $format The file format, either "csv" or "json".
     *
     * @return bool True if the file was written successfully; false otherwise.
     */
    public function writeToFile(Builder $query, string $projectDir, string $fileName, string $format): bool
    {
        // Make directory(recursive)  if it doesn't already exist,
        // directory will be like 2a58ddf888a04268b1545553dda88f28/123
        // {project_ref}/{user_id}
        if (!File::exists($projectDir)) {
            if (!File::makeDirectory($projectDir, 0755, true)) {
                return false;
            }
        }

        $outputFile = $projectDir . '/' . $fileName . '.' . $format;
        switch ($format) {
            case 'csv':
                return $this->writeCSV($query, $outputFile);
            case 'json':
                return $this->writeJSON($query, $outputFile);
        }
        return false;
    }

    public function writeCSV(Builder $query, string $outputFile): bool
    {
        DB::disableQueryLog();

        $startTime = microtime(true);
        $memoryUsageStart = memory_get_usage();
        $access = $this->project->isPrivate() ? 'private' : 'public';

        $file = null; // Declare file variable

        try {
            // Open file for writing
            $file = fopen($outputFile, "w");
            if (!$file) {
                throw new RuntimeException("Failed to open file: $outputFile");
            }

            // Acquire an exclusive lock
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException("Failed to acquire file lock");
            }

            // Add BOM for Excel UTF-8 compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Add CSV headers
            fputcsv($file, $this->dataMappingService->getHeaderRowCSV(), ',');

            $chunkSize = config('epicollect.limits.download_entries_chunk_size');
            $rowCount = 0;

            foreach ($query->lazyByIdDesc() as $entry) {
                $rowCount++;
                fputcsv(
                    $file,
                    $this->dataMappingService->getMappedEntryCSV(
                        $entry->entry_data,
                        $entry->user_id,
                        $entry->title,
                        $entry->uploaded_at,
                        $access,
                        $entry->branch_counts ?? null
                    ),
                    ','
                );
                $entry = null; // Avoid memory leaks
            }

            // Log execution time and memory usage
            $totalTime = microtime(true) - $startTime;
            $memoryUsageEnd = memory_get_usage();

            Log::info(__METHOD__ . ' write completed', [
                'path' => $outputFile,
                'chunk_size' => $chunkSize,
                'total_rows' => $rowCount,
                'total_time' => round($totalTime / 60, 2) . ' minutes',
                'total_memory_usage' => Common::formatBytes($memoryUsageEnd - $memoryUsageStart),
                'peak_memory_usage' => Common::formatBytes(memory_get_peak_usage()),
            ]);

            $this->totalDuration += $totalTime;
            $this->totalEntries += $rowCount;

            return true;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return false;
        } finally {
            if (isset($file) && is_resource($file)) {
                fflush($file);
                flock($file, LOCK_UN);
                fclose($file);
            }
        }
    }


    /**
     * Writes the query results to a JSON file.
     *
     * This function processes entries lazily in defined chunks, maps each entry into JSON format, and writes them into a file as a JSON array under the key "data". Data is buffered and flushed to the file when it reaches approximately 1 MB, ensuring efficient file I/O. The file is locked during writing to maintain consistency, and performance metrics such as execution time and memory usage are logged.
     *
     * @param Builder $query Query builder instance used to fetch entries.
     * @param string $outputFile The path where the JSON file will be created.
     * @return bool True if the JSON data was written successfully; false if an error occurred.
     */
    public function writeJSON(Builder $query, string $outputFile): bool
    {
        $startTime = microtime(true); // Start time
        $memoryUsageStart = memory_get_usage(); // Initial memory usage
        $access = $this->project->isPrivate() ? 'private' : 'public';

        try {
            $file = fopen($outputFile, "w");
            if (!$file) {
                throw new RuntimeException("Failed to open file: $outputFile");
            }

            // Acquire an exclusive lock
            if (flock($file, LOCK_EX)) {
                // Write the opening JSON structure
                fwrite($file, '{"data": [');

                $chunkSize = config('epicollect.limits.download_entries_chunk_size');
                $buffer = '';
                $rowCount = 0; // Track the number of rows processed
                $total = $query->count('id'); // Get total number of entries

                foreach ($query->lazyByIdDesc($chunkSize) as $entry) {

                    // Whether to append a comma or not
                    $append = ($rowCount < $total - 1) ? ',' : '';

                    // Add the entry to the buffer
                    $buffer .= $this->dataMappingService->getMappedEntryJSON(
                        $entry->entry_data,
                        $entry->user_id,
                        $entry->title,
                        $entry->uploaded_at,
                        $access,
                        $entry->branch_counts ?? null
                    ) . $append;
                    $entry = null;

                    $rowCount++;

                    // Write buffer to file when it reaches a certain size
                    if (strlen($buffer) >= (1024 * 1024 * 10)) { // 1 MB
                        fwrite($file, $buffer);
                        $buffer = null; // Clear the buffer
                    }
                }

                // Write any remaining data in the buffer
                if (!empty($buffer)) {
                    fwrite($file, $buffer);
                    $buffer = null;
                }

                // Write the closing JSON structure
                fwrite($file, ']}');

                fflush($file);
                flock($file, LOCK_UN);
            } else {
                throw new RuntimeException("Failed to acquire file lock");
            }

            fclose($file);

            // Log total execution time and memory usage
            $totalTime = microtime(true) - $startTime;
            $memoryUsageEnd = memory_get_usage();
            Log::info(__METHOD__ . ' write completed', [
                'path' => $outputFile,
                'chunk_size' => $chunkSize,
                'total_rows' => $rowCount,
                'total_time' => round($totalTime / 60, 2) . ' minutes',
                'total_memory_usage' => Common::formatBytes($memoryUsageEnd - $memoryUsageStart),
                'peak_memory_usage' => Common::formatBytes(memory_get_peak_usage()),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('writeJSON failed', ['exception' => $e->getMessage()]);
            if (isset($file)) {
                fclose($file);
            }
            return false;
        }
    }
}
