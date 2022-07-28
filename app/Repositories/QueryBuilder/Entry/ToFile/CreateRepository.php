<?php

namespace ec5\Repositories\QueryBuilder\Entry\ToFile;

use Illuminate\Support\Collection;
use ec5\Models\ProjectData\DataMappingHelper;

use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository as EntrySearch;
use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository as BranchEntrySearch;

use Auth;
use DB;
use Config;
use File;
use Illuminate\Support\Str;
use Illuminate\Database\Query\Builder;
use Log;
use ec5\Libraries\Utilities\Common;

class CreateRepository
{

    /**
     * @var Project
     */
    protected $project;

    /**
     * @var DataMappingHelper
     */
    protected $dataMappingHelper = null;

    /**
     * @var EntrySearch
     */
    protected $entrySearch;

    /**
     * @var BranchEntrySearch
     */
    protected $branchEntrySearch;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * CreateRepository constructor.
     * @param DataMappingHelper $dataMappingHelper
     * @param EntrySearch $entrySearch
     * @param BranchEntrySearch $branchEntrySearch
     */
    public function __construct(
        DataMappingHelper $dataMappingHelper,
        EntrySearch $entrySearch,
        BranchEntrySearch $branchEntrySearch
    ) {
        $this->entrySearch = $entrySearch;
        $this->branchEntrySearch = $branchEntrySearch;
        $this->dataMappingHelper = $dataMappingHelper;
        DB::connection()->enableQueryLog();
    }

    /**
     * Try and create all files
     *
     * @param Project $project
     * @param $projectDir
     * @param $options
     * @return bool
     */
    public function create(Project $project, $projectDir, $options)
    {
        // Set default sort order
        $options['entry_col'] = 'created_at';
        $options['sort_order'] = 'DESC';

        $this->project = $project;
        $projectExtra = $project->getProjectExtra();

        // Shall we delete all files for this user?
        $this->deleteFiles($projectDir);

        $format = $options['format'];
        $mapIndex = $options['map_index'];

        $forms = $projectExtra->getForms();
        $formCount = 1;
        $branchCount = 1;

        foreach ($forms as $formRef => $value) {

            // Set the form ref into the options
            $options['form_ref'] = $formRef;

            // Lets start with entryType Form
            $entryType = Config::get('ec5Strings.form');

            $fileName = $this->generateFilename($entryType . '-' . $formCount, $value['details']['slug']);

            // Set the mapping
            $this->dataMappingHelper->initialiseMapping($this->project, $format, $entryType, $formRef, null, $mapIndex);

            $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at'];

            // Get the query for these entries
            $query = $this->entrySearch->getEntriesForForm($this->project->getId(), $options, $columns);

            // Write to file
            if (!$this->writeToFile($query, $projectDir, $fileName, $format)) {
                return false;
            }
            // Get any branches
            $branches = array_keys($value['branch']);

            foreach ($branches as $branchInputRef) {

                // Set the branch ref into the options
                $options['branch_ref'] = $branchInputRef;

                // Switch entry type
                $entryType = Config::get('ec5Strings.branch');

                $branchQuestion = $projectExtra->getInputDetail($branchInputRef, 'question');

                $fileName = $this->generateFilename($entryType . '-' . $branchCount, $branchQuestion);

                // Set the mapping
                $this->dataMappingHelper->initialiseMapping(
                    $this->project,
                    $format,
                    $entryType,
                    $formRef,
                    $branchInputRef,
                    $mapIndex
                );

                $columns = ['uuid', 'title', 'entry_data', 'user_id', 'uploaded_at'];

                // Get the query for these branch entries
                $query = $this->branchEntrySearch->getBranchEntriesForBranchRef(
                    $this->project->getId(),
                    $options,
                    $columns
                );

                // Write to file
                if (!$this->writeToFile($query, $projectDir, $fileName, $format)) {
                    return false;
                }
                // Increment the branches
                $branchCount++;
            }

            // Increment the forms
            $formCount++;
        }

        $zipFileName = $project->slug . '-' . $format . '.zip';

        $this->zipFile($projectDir, $zipFileName, $format);

        return true;
    }

    /**
     * @param $projectDir
     * @param $zipFileName
     * @param $format
     */
    private function zipFile($projectDir, $zipFileName, $format)
    {
        $zip = new \ZipArchive();
        $zip->open($projectDir . '/' . $zipFileName, \ZipArchive::CREATE);
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
     * @param $projectDir
     */
    public function zipExists($projectDir)
    {
        File::delete(File::glob($projectDir . '/*.*'));
    }

    /**
     * Generate a unique filename
     *
     * @param $prefix
     * @param $f
     * @param $ref
     * @return string
     */
    protected function generateFilename($prefix, $f)
    {
        /**
         *  Truncate anything bigger than 100 chars
         *  to keep the filename unique, a prefix is passed
         *
         *  We do this as we might have filename too long (i.e branch question of 255,
         *  then adding prefix we go over the max filename length (255)
         */

        return $prefix . '__' . Str::slug(substr(strtolower($f), 0, 100));
    }

    /**
     * @param $projectDir
     */
    protected function deleteFiles($projectDir)
    {
        File::delete(File::glob($projectDir . '/*.*'));
    }

    /** mkdir if not there
     *
     * @param $path
     * @return bool
     */
    protected function makeDir($path)
    {
        return is_dir($path) || mkdir($path);
    }

    /**
     * @param Builder $query
     * @param $projectDir
     * @param $fileName
     * @param $format
     * @return bool
     */

    public function writeToFile(Builder $query, $projectDir, $fileName, $format)
    {
        // Make directory(recursive)  if it doesn't already exist
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
                return $this->writeToFileCSV($query, $outputFile);
            case 'json':
                return $this->writeToFileJSON($query, $outputFile);
        }
        return false;
    }

    /**
     * @param Builder $query
     * @param $outputFile
     * @return bool
     */
    public function writeToFileCSV(Builder $query, $outputFile)
    {

        //check memory consumption
        //  LOG::error('Usage: '.Common::formatBytes(memory_get_usage()));
        //  LOG::error('Peak Usage: '.Common::formatBytes(memory_get_peak_usage()));

        $file = fopen($outputFile, "w");

        // Acquire an exclusive lock
        if (flock($file, LOCK_EX)) {

            //Add BOM for Excel (UTF-8 languages do not display correctly by default)
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, $this->dataMappingHelper->headerRowCsv(), ',');
            $chunkLimit = config('ec5Limits.download_entries_chunk_limit');


            $query->chunk(
                $chunkLimit,
                function (Collection $entries) use (&$count, $file) {
                    //check memory consumption
                    //LOG::error('Usage: '.Common::formatBytes(memory_get_usage()));
                    // LOG::error('Peak Usage: '.Common::formatBytes(memory_get_peak_usage()));
                    foreach ($entries as $entry) {

                        if (
                            fputcsv($file, $this->dataMappingHelper->swapOutEntryCsv(
                                $entry->entry_data,
                                $entry->branch_counts ?? null,
                                $entry->user_id,
                                $entry->title,
                                $entry->uploaded_at
                            ), ',') === false
                        ) {
                            // Error
                            $this->errors[] = 'ec5_232';
                            return;
                        }
                    }
                }
            );

            if ($this->hasErrors()) {
                fclose($file);
                return false;
            }

            fflush($file);
            flock($file, LOCK_UN);

            //check memory consumption
            //  LOG::error('Usage: '.Common::formatBytes(memory_get_usage()));
            //   LOG::error('Peak Usage: '.Common::formatBytes(memory_get_peak_usage()));

        } else {
            $this->errors[] = 'ec5_232';
            fclose($file);
            return false;
        }

        fclose($file);
        return true;
    }

    /**
     * @param Builder $query
     * @param $outputFile
     * @return bool
     */
    public function writeToFileJSON(Builder $query, $outputFile)
    {

        $file = fopen($outputFile, "w");

        // Acquire an exclusive lock
        if (flock($file, LOCK_EX)) {

            $count = 1;
            fwrite($file, '{"data": [');

            // Get total of entries
            $total = $query->count('id');
            $chunkLimit = Config::get('ec5Limits.download_entries_chunk_limit');

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
                        fwrite($file, $this->dataMappingHelper->swapOutEntryJson(
                            $entry->entry_data,
                            $entry->branch_counts ?? null,
                            $entry->user_id,
                            $entry->title,
                            $entry->uploaded_at
                        ) . $append);
                    }
                }
            );
            // todo - check memory consumption
            //            var_dump(memory_get_usage());
            //            var_dump(memory_get_peak_usage());

            fwrite($file, ']}');

            fflush($file);
            flock($file, LOCK_UN);
        } else {
            $this->errors[] = 'ec5_232';
            fclose($file);
            return false;
        }

        fclose($file);
        return true;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return count($this->errors) > 0;
    }

    /**
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }
}
