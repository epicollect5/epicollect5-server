<?php

namespace ec5\Http\Controllers\Api\Entries;

use Cookie;
use ec5\Http\Validation\Entries\Download\RuleDownloadSubset;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Services\Entries\EntriesViewService;
use ec5\Services\Mapping\DataMappingService;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\Request;
use League\Csv\Writer;
use Log;
use Ramsey\Uuid\Uuid;
use Response;
use Storage;
use Throwable;
use ZipArchive;

class DownloadSubsetController
{
    use RequestAttributes;

    protected DataMappingService $dataMappingService;
    protected array $errors = [];

    public function __construct(DataMappingService $dataMappingService)
    {
        $this->dataMappingService = $dataMappingService;
    }

    public function subset(Request $request, RuleDownloadSubset $ruleDownloadSubset, EntriesViewService $entriesViewService)
    {
        // Check the mapping is valid
        $projectMapping = $this->requestedProject()->getProjectMapping();

        $allowedKeys = array_keys(config('epicollect.strings.download_subset_entries'));
        $perPage = config('epicollect.limits.entries_table.per_page');
        $params = $entriesViewService->getSanitizedQueryParams($allowedKeys, $perPage);

        //Get raw query params, $this->getRequestParams is doing some filtering
        $rawParams = $request->all();
        $cookieName = config('epicollect.setup.cookies.download_entries');

        // Validate the options and query string
        if (!$entriesViewService->areValidQueryParams($params)) {
            return Response::apiErrorCode(400, $entriesViewService->validationErrors);
        }

        $ruleDownloadSubset->validate($rawParams);
        if ($ruleDownloadSubset->hasErrors()) {
            return Response::apiErrorCode(400, $ruleDownloadSubset->errors());
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
            return Response::apiErrorCode(400, ['map_index: ' . $params['map_index'] => ['ec5_322']]);
        }

        $this->dataMappingService->init(
            $this->requestedProject(),
            $params['format'],
            $params['branch_ref'] !== '' ? 'branch' : 'form',
            $params['form_ref'],
            $params['branch_ref'],
            $params['map_index']
        );

        if ($params['branch_ref'] !== '') {
            $columns = ['uuid', 'title', 'entry_data', 'user_id', 'uploaded_at'];
            // Get the query for these branch entries
            $query = BranchEntry::getBranchEntriesByBranchRef(
                $this->requestedProject()->getId(),
                $params,
                $columns
            );
        } else {
            //hierarchy
            $columns = ['title', 'entry_data', 'branch_counts', 'child_counts', 'user_id', 'uploaded_at'];
            // Get the query for these entries
            $query = Entry::getEntriesByForm($this->requestedProject()->getId(), $params, $columns);
        }

        $filepath = $this->createSubsetArchive($query, $filename);

        if (count($this->errors) > 0) {
            return Response::apiErrorCode(400, $this->errors);
            //todo should I delete any leftovers here?
        }
        $mediaCookie = Common::getMediaCookie($timestamp);
        Cookie::queue($mediaCookie);

        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
    }

    private function createSubsetArchive($query, $filename): string
    {
        $exportChunk = config('epicollect.limits.entries_export_chunk');
        $projectRef = $this->requestedProject()->ref;
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
            '',
            [
            'visibility' => 'public',
                    'directory_visibility' => 'public'
                ]
        );

        //get handle of empty file just created
        $CSVfilepath = Storage::disk('temp')
                ->path('')
            . 'subset/' . $projectRef . '/' . $csvFilename;

        $zipFilepath = Storage::disk('temp')
                ->path('')
            . 'subset/' . $projectRef . '/' . $zipFilename;

        try {
            //create empty zip file
            $zip->open($zipFilepath, ZipArchive::CREATE);
            //write to file one row at a time to keep memory usage low
            $csv = Writer::createFromPath($CSVfilepath, 'w+');

            //write headers
            $csv->insertOne($this->dataMappingService->getHeaderRowCSV());
            //chuck feature keeps memory usage low
            $query->chunk($exportChunk, function ($entries) use ($csv) {
                foreach ($entries as $entry) {
                    $csv->insertOne($this->dataMappingService->getMappedEntryCSV(
                        $entry->entry_data,
                        $entry->user_id,
                        $entry->title,
                        $entry->uploaded_at,
                        $entry->branch_counts ?? null
                    ));
                }
                //   \LOG::error('Usage: ' . Common::formatBytes(memory_get_usage()));
                //     \LOG::error('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));
            });
        } catch (Throwable $e) {
            // Error writing to file
            Log::error('createSubsetArchive failure', [
                'exception' => $e->getMessage()
            ]);
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
