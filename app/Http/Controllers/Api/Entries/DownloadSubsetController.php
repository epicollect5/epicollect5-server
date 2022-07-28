<?php

namespace ec5\Http\Controllers\Api\Entries;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;

use ec5\Http\Controllers\Api\Entries\View\EntrySearchControllerBase;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Http\Validation\Entries\Search\RuleQueryString;
use ec5\Http\Validation\Entries\Download\RuleDownloadSubset as DownloadSubsetValidator;
use Illuminate\Support\Collection;


use ec5\Libraries\Utilities\Common;
use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository;
use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository;
use ec5\Repositories\QueryBuilder\Entry\ToFile\CreateRepository as FileCreateRepository;
use ec5\Models\ProjectData\DataMappingHelper;

use Illuminate\Http\Request;

use ec5\Models\Eloquent\ProjectStructure;
use ZipArchive;

use Config;
use Storage;
use Cookie;
use Illuminate\Support\Str;
use League\Csv\Writer;
use SplTempFileObject;
use Auth;
use Ramsey\Uuid\Uuid;


class DownloadSubsetController extends EntrySearchControllerBase
{

    protected $fileCreateRepository;
    protected $allowedSearchKeys;
    protected $dataMappingHelper;

    /**
     * DownloadController constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param EntryRepository $entryRepository
     * @param BranchEntryRepository $branchEntryRepository
     * @param RuleQueryString $ruleQueryString
     * @param RuleAnswers $ruleAnswers
     * @param FileCreateRepository $fileCreateRepository
     */
    public function __construct(
        Request $request,
        ApiRequest $apiRequest,
        ApiResponse $apiResponse,
        EntryRepository $entryRepository,
        BranchEntryRepository $branchEntryRepository,
        RuleQueryString $ruleQueryString,
        RuleAnswers $ruleAnswers,
        FileCreateRepository $fileCreateRepository,
        DataMappingHelper $dataMappingHelper

    ) {
        parent::__construct(
            $request,
            $apiRequest,
            $apiResponse,
            $entryRepository,
            $branchEntryRepository,
            $ruleQueryString,
            $ruleAnswers
        );

        $this->allowedSearchKeys = Config::get('ec5Enums.download_subset_entries');
        $this->fileCreateRepository = $fileCreateRepository;
        $this->dataMappingHelper = $dataMappingHelper;
    }

    public function subset(Request $request, DownloadSubsetValidator $validator)
    {
        // Check the mapping is valid
        $projectMapping = $this->requestedProject->getProjectMapping();
        $params = $this->getRequestOptions($request, Config::get('ec5Limits.entries_table.per_page'));

        //Get raw query params,  $this->getRequestOptions is doing some filtering
        $rawParams = $request->all();

        $cookieName = Config::get('ec5Strings.cookies.download-entries');

        // Validate the options and query string
        if (!$this->validateOptions($params)) {
            return $this->apiResponse->errorResponse(400, $this->validateErrors);
        }

        $validator->validate($rawParams);
        if ($validator->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $validator->errors());
        }

        $timestamp = $request->query($cookieName);
        $filename = $params['filename'];
        if ($timestamp) {
            //check if the timestamp is valid
            if (!Common::isValidTimestamp($timestamp) && strlen($timestamp) === 13) {
                abort(404); //so it goes to an error page
            }
        } else {
            //error no timestamp was passed
            abort(404); //s it goes to an error page
        }

        // If the map_index value passed does not exist, error out
        if (!in_array($params['map_index'], $projectMapping->getMapIndexes())) {
            return $this->apiResponse->errorResponse(400, ['map_index: ' . $params['map_index'] => ['ec5_322']]);
        }

        // Set the mapping
        $this->dataMappingHelper->initialiseMapping(
            $this->requestedProject,
            $params['format'],
            $params['branch_ref'] !== '' ? 'branch' : 'form',
            $params['form_ref'],
            $params['branch_ref'],
            $params['map_index']
        );

        if ($params['branch_ref'] !== '') {
            //branch
            $query = $this->getBranchEntriesQuery($params);
        } else {
            //hierarchy
            $query = $this->getEntriesQuery($params);
        }

        $filepath = $this->writeFileCsvZipped($query, $filename);

        if (count($this->errors) > 0) {
            return $this->apiResponse->errorResponse(400, $this->errors);
            //todo should I delete any leftovers here?
        }

        //"If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes)."
        $mediaCookie = Cookie::make($cookieName, $timestamp, 0, null, null, false, false);
        Cookie::queue($mediaCookie);


        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        //        return response((string)$file, 200, [
        //            'Content-Type' => 'text/csv',
        //            'Content-Transfer-Encoding' => 'binary',
        //            'Content-Disposition' => 'attachment'
        //        ]);
    }

    private function getEntriesQuery(array $params)
    {
        $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at'];
        $query = $this->runQuery($params, $columns);

        return $query;
    }

    private function getBranchEntriesQuery(array $params)
    {
        $columns = ['title', 'entry_data', 'user_id', 'uploaded_at'];
        $query = $this->runQueryBranch($params, $columns);

        return $query;
    }

    private function writeFileCsvZipped($query, $filename)
    {
        $exportChunk = Config::get('ec5Limits.entries_export_chunk');
        $projectRef = $this->requestedProject->ref;
        //generate unique temp file name to cover concurrent users per project
        $csvFilename = Uuid::uuid4()->toString() . '.csv';
        $zipFilename = Uuid::uuid4()->toString() . '.zip';
        $zip = new ZipArchive();

        //check memory consumption
        //        \LOG::error('Usage: ' . Common::formatBytes(memory_get_usage()));
        //        \LOG::error('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));

        //create an empty csv file in the temp/subset/{$project_ref} folder
        Storage::disk('temp')->put(
            'subset/' . $projectRef . '/' . $csvFilename,
            ''
        );

        //get handle of empty file just created
        $CSVfilepath = Storage::disk('temp')
            ->getAdapter()
            ->getPathPrefix()
            . 'subset/' . $projectRef . '/' . $csvFilename;

        $zipFilepath = Storage::disk('temp')
            ->getAdapter()
            ->getPathPrefix()
            . 'subset/' . $projectRef . '/' . $zipFilename;


        //create empty zip file
        $zip->open($zipFilepath, \ZipArchive::CREATE);


        //write to file one row at a time to keep memory usage low
        $csv = Writer::createFromPath($CSVfilepath, 'w+');

        try {
            //write headers
            $csv->insertOne($this->dataMappingHelper->headerRowCsv());

            //chuck feature keeps memory usage low
            $query->chunk($exportChunk, function ($entries) use ($csv) {
                foreach ($entries as $entry) {
                    $csv->insertOne($this->dataMappingHelper->swapOutEntryCsv(
                        $entry->entry_data,
                        $entry->branch_counts ?? null,
                        $entry->user_id,
                        $entry->title,
                        $entry->uploaded_at
                    ));
                }
                //   \LOG::error('Usage: ' . Common::formatBytes(memory_get_usage()));
                //     \LOG::error('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));
            });
        } catch (\Exception $e) {
            // Error writing to file
            $this->errors['entries-subset-csv'] = ['ec5_83'];
        }

        $zip->addFile($CSVfilepath, basename(str_replace('.zip', '.csv', $filename)));
        $zip->close();

        //delete temp csv file
        Storage::disk('temp')->delete('subset/' . $projectRef . '/' . $csvFilename);

        //        \LOG::error('Usage: ' . Common::formatBytes(memory_get_usage()));
        //        \LOG::error('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));
        return $zipFilepath;
    }
}
