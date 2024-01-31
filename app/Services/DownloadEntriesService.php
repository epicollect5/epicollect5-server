<?php

namespace ec5\Services;

use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use Exception;
use File;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Log;
use Storage;
use ZipArchive;

class DownloadEntriesService
{
    protected $project;
    protected $dataMappingService;
    protected $errors = [];

    public function __construct(DataMappingService $dataMappingService)
    {
        $this->dataMappingService = $dataMappingService;
    }

    /**
     * Try and create all files
     */
    public function createArchive(ProjectDTO $project, $projectDir, $params): bool
    {
        // Set default sort order
        $params['entry_col'] = 'created_at';
        $params['sort_order'] = 'DESC';
        $this->project = $project;
        //delete all existing files for this user
        Storage::deleteDirectory($projectDir);

        $format = $params['format'];
        $mapIndex = $params['map_index'];


        $forms = $project->getProjectDefinition()->getData()['project']['forms'];
        $formCount = 1;
        $branchCount = 1;
        foreach ($forms as $form) {
            // Set the form ref into the params
            $params['form_ref'] = $form['ref'];
            // Let's start with forms first
            $prefix = config('epicollect.strings.form') . '-' . $formCount;
            $fileName = Common::generateFilename($prefix, $form['slug']);
            // Set the mapping
            $this->dataMappingService->init($this->project, $format, config('epicollect.strings.form'), $form['ref'], null, $mapIndex);

            $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at'];
            // Get the query for these entries
            $query = Entry::getEntriesByForm($this->project->getId(), $params, $columns);
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

                $columns = ['uuid', 'title', 'entry_data', 'user_id', 'uploaded_at'];

                // Get the query for these branch entries
                $query = BranchEntry::getBranchEntriesByBranchRef(
                    $this->project->getId(),
                    $params,
                    $columns
                );

                // Write to file
                if (!$this->writeToFile($query, $projectDir, $fileName, $format)) {
                    return false;
                }
                $branchCount++;
            }
            $formCount++;
        }
        try {
            $this->buildZipArchive($projectDir, $project->slug, $format);
        } catch (Exception $e) {
            Log::error('buildZipArchive failed', ['exception' => $e->getMessage()]);
            return false;
        }
        return true;
    }

    private function buildZipArchive($projectDir, $projectSlug, $format)
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

    public function writeToFile(Builder $query, $projectDir, $fileName, $format): bool
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

    public function writeCSV(Builder $query, $outputFile): bool
    {
        //check memory consumption
        //  LOG::error('Usage: '.Common::formatBytes(memory_get_usage()));
        //  LOG::error('Peak Usage: '.Common::formatBytes(memory_get_peak_usage()));
        try {
            $file = fopen($outputFile, "w");
            // Acquire an exclusive lock
            if (flock($file, LOCK_EX)) {
                //Add BOM for Excel (UTF-8 languages do not display correctly by default)
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($file, $this->dataMappingService->getHeaderRowCSV());
                $chunkLimit = config('epicollect.limits.download_entries_chunk_limit');

                $query->chunk(
                    $chunkLimit,
                    function (Collection $entries) use (&$count, $file) {
                        //check memory consumption
                        //LOG::error('Usage: '.Common::formatBytes(memory_get_usage()));
                        // LOG::error('Peak Usage: '.Common::formatBytes(memory_get_peak_usage()));
                        foreach ($entries as $entry) {
                            if (
                                fputcsv($file, $this->dataMappingService->getMappedEntryCSV(
                                    $entry->entry_data,
                                    $entry->user_id,
                                    $entry->title,
                                    $entry->uploaded_at,
                                    $entry->branch_counts ?? null
                                )) === false
                            ) {
                                fclose($file);
                                return false;
                            }
                        }
                    }
                );

                fflush($file);
                flock($file, LOCK_UN);
                //  LOG::error('Usage: '.Common::formatBytes(memory_get_usage()));
                //   LOG::error('Peak Usage: '.Common::formatBytes(memory_get_peak_usage()));
            } else {
                fclose($file);
                return false;
            }
            fclose($file);
            return true;
        } catch (Exception $e) {
            Log::error('writeCSV failed', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function writeJSON(Builder $query, $outputFile): bool
    {
        try {
            $file = fopen($outputFile, "w");
            // Acquire an exclusive lock
            if (flock($file, LOCK_EX)) {
                $count = 1;
                fwrite($file, '{"data": [');
                // Get total of entries
                $total = $query->count('id');
                $chunkLimit = config('epicollect.limits.download_entries_chunk_limit');
                $query->chunk(
                    $chunkLimit,
                    function (Collection $entries) use (&$count, $total, $file) {
                        foreach ($entries as $entry) {
                            // Whether to append comma or not
                            $append = ',';
                            if ($count == $total) {
                                $append = '';
                            }
                            $count++;
                            // Write row to file
                            fwrite($file, $this->dataMappingService->getMappedEntryJSON(
                                    $entry->entry_data,
                                    $entry->user_id,
                                    $entry->title,
                                    $entry->uploaded_at,
                                    $entry->branch_counts ?? null
                                ) . $append);
                        }
                    }
                );
                fwrite($file, ']}');
                fflush($file);
                flock($file, LOCK_UN);
            } else {
                fclose($file);
                return false;
            }
            fclose($file);
            return true;
        } catch (Exception $e) {
            Log::error('writeJSON failed', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}