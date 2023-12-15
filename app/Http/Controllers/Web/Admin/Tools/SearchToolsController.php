<?php

namespace ec5\Http\Controllers\Web\Admin\Tools;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use DB;
use ec5\Http\Controllers\Controller;
use ec5\Libraries\DirectoryGenerator\DirectoryGenerator;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Eloquent\System\StorageStats;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use League\Csv\Writer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Storage;
use ZipArchive;

class SearchToolsController extends Controller
{

    use DirectoryGenerator;

    public function findQuestionsWithTooManyJumps()
    {
        $wrongProjects = [];

        $projects = DB::select('SELECT project_definition FROM project_structures');

        foreach ($projects as $project) {

            $projectDefinition = json_decode($project->project_definition, true);
            $projectName = $projectDefinition['project']['name'];
            $form = $projectDefinition['project']['forms'][0];
            $inputs = $form['inputs'];

            foreach ($inputs as $key => $input) {

                $possibleAnswers = $input['possible_answers'];
                $jumps = $input['jumps'];

                if (count($jumps) > count($possibleAnswers)) {

                    if ($input['type'] === 'dropdown' || $input['type'] === 'radio' || $input['type'] === 'checkbox') {

                        array_push($wrongProjects, [
                            'project' => $projectName,
                            'question' => $input['question']
                        ]);
                    }
                }

                if ($input['type'] === 'branch') {
                    foreach ($input['branch'] as $branchInput) {

                        $possibleAnswers = $branchInput['possible_answers'];
                        $jumps = $branchInput['jumps'];
                        if (count($jumps) > count($possibleAnswers)) {
                            if ($input['type'] === 'dropdown' || $input['type'] === 'radio' || $input['type'] === 'checkbox') {

                                array_push($wrongProjects, [
                                    'project' => $projectName,
                                    'question' => $input['question']
                                ]);
                            }
                        }

                        if ($input['type'] === 'group') {
                            foreach ($input['group'] as $key => $groupInput) {

                                $possibleAnswers = $groupInput['possible_answers'];
                                $jumps = $groupInput['jumps'];
                                if (count($jumps) > count($possibleAnswers)) {
                                    if ($input['type'] === 'dropdown' || $input['type'] === 'radio' || $input['type'] === 'checkbox') {
                                        array_push($wrongProjects, [
                                            'project' => $projectName,
                                            'question' => $input['question']
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                if ($input['type'] === 'group') {
                    foreach ($input['group'] as $groupInput) {

                        $possibleAnswers = $groupInput['possible_answers'];
                        $jumps = $groupInput['jumps'];
                        if (count($jumps) > count($possibleAnswers)) {
                            if ($input['type'] === 'dropdown' || $input['type'] === 'radio' || $input['type'] === 'checkbox') {

                                array_push($wrongProjects, [
                                    'project' => $projectName,
                                    'question' => $input['question']
                                ]);
                            }
                        }
                    }
                }
            }
        }


        return $wrongProjects;
    }

    public function countMedia($days = 1)
    {
        $projectsWithMedia = [];

        $createdAt = Carbon::now()->subDays($days);

        $mediaTypes = ['photo', 'video', 'audio'];

        //find recent projects (last 30 days) based on entries uploaded recently
        $recentActiveProjects = DB::table('entries')->where('uploaded_at', '>=', $createdAt)
            ->groupBy('project_id')->distinct()->pluck('project_id');

        $projects = DB::table('projects')
            ->join('project_structures', 'projects.id', '=', 'project_structures.project_id')
            // ->where('projects.created_at', '>=', $createdAt)
            ->whereIn('projects.id', $recentActiveProjects)
            ->get();

        foreach ($projects as $project) {

            $projectDefinition = json_decode($project->project_definition);

            foreach ($projectDefinition->project->forms as $form) {

                foreach ($form->inputs as $inputs) {

                    if (in_array($inputs->type, $mediaTypes)) {

                        //count how many files per each media type
                        $audios = count(Storage::disk('audio')
                            ->allFiles($projectDefinition->project->ref));

                        $videos = count(Storage::disk('video')
                            ->allFiles($projectDefinition->project->ref));

                        $photos = count(Storage::disk('entry_original')
                            ->allFiles($projectDefinition->project->ref));

                        array_push($projectsWithMedia, [
                            'files_total' => $audios + $photos + $videos,
                            'name' => $projectDefinition->project->name,
                            'id' => $project->id,
                            'ref' => $projectDefinition->project->ref,
                            'files' => [
                                'audio' => $audios,
                                'photo' => $photos,
                                'video' => $videos
                            ]
                        ]);
                        break 2;
                    }
                }
            }
        }

        //see t.ly/B8FI
        usort($projectsWithMedia, function ($item1, $item2) {
            return $item2['files_total'] <=> $item1['files_total'];
        });

        return $projectsWithMedia;
    }

    //find project with jumps (only first form is checked)
    public function findProjectsWithJumps()
    {
        $projectsWithJumps = [];

        $projects = DB::select('SELECT project_definition FROM project_structures');

        foreach ($projects as $project) {

            $projectDefinition = json_decode($project->project_definition, true);
            $projectName = $projectDefinition['project']['name'];
            $form = $projectDefinition['project']['forms'][0];
            $inputs = $form['inputs'];

            foreach ($inputs as $key => $input) {

                $jumps = $input['jumps'];

                if (count($jumps) > 0) {

                    array_push($projectsWithJumps, [
                        'project' => $projectName,
                        'jumps_found' => count($jumps)
                    ]);
                }

                //                if ($input['type'] === 'branch') {
                //                    foreach ($input['branch'] as $branchInput) {
                //
                //                        $jumps = $branchInput['jumps'];
                //                        if (count($jumps) > 0) {
                //
                //                            array_push($projectsWithJumps, [
                //                                'project' => $projectName,
                //                                'jumps_found' => count($jumps)
                //                            ]);
                //                        }
                //                    }
                //                }
            }
        }

        function sortAssociativeArrayByKey($array, $key, $direction = 'ASC')
        {

            switch ($direction) {
                case 'ASC':
                    usort($array, function ($first, $second) use ($key) {
                        return $first[$key] <=> $second[$key];
                    });
                    break;
                case 'DESC':
                    usort($array, function ($first, $second) use ($key) {
                        return $second[$key] <=> $first[$key];
                    });
                    break;
                default:
                    break;
            }

            return $array;
        }

        return sortAssociativeArrayByKey($projectsWithJumps, 'jumps_found', 'DESC');
    }

    //find the projects with a high number of questions
    public function findprojectsWithALotOfQuestions()
    {
        $projects = DB::select('SELECT project_definition FROM project_structures');
        $projectsWithALotOfInputs = [];

        foreach ($projects as $project) {

            $projectDefinition = json_decode($project->project_definition, true);
            $projectName = $projectDefinition['project']['name'];
            $forms = $projectDefinition['project']['forms'];
            $inputsTotal = 0;
            $possibleAnswersTotal = 0;

            foreach ($forms as $formKey => $form) {

                $inputs = $form['inputs'];

                foreach ($inputs as $inputKey => $input) {

                    $inputsTotal++;
                    $possibleAnswersTotal += sizeOf($input['possible_answers']);

                    if ($input['type'] === 'branch') {
                        foreach ($input['branch'] as $branchInput) {

                            $inputsTotal++;
                            $possibleAnswersTotal += sizeOf($branchInput['possible_answers']);

                            if ($input['type'] === 'group') {
                                foreach ($input['group'] as $key => $groupInput) {
                                    $inputsTotal++;
                                    $possibleAnswersTotal += sizeOf($groupInput['possible_answers']);
                                }
                            }
                        }
                    }

                    if ($input['type'] === 'group') {
                        foreach ($input['group'] as $groupInput) {
                            $inputsTotal++;
                            $possibleAnswersTotal += sizeOf($groupInput['possible_answers']);
                        }
                    }
                }
            }

            if ($inputsTotal > 100) {
                array_push($projectsWithALotOfInputs, [
                    'project' => $projectName,
                    'inputs' => $inputsTotal,
                    'possible_answers' => $possibleAnswersTotal
                ]);
            }
        }

        //sort array
        usort($projectsWithALotOfInputs, array($this, "cmp"));

        return $projectsWithALotOfInputs;
    }

    //order by the one with the most possible answers
    public function cmp($a, $b)
    {
        return $a['possible_answers'] < $b['possible_answers'];
    }

    //find the projects with uniqueness set on TIME question
    public function findprojectsWithTimeUniqueness()
    {
        $projects = DB::select('SELECT project_definition, project_id FROM project_structures');

        $projectsWithTimeUniqueness = [];
        $projectIds = [];

        foreach ($projects as $project) {

            $projectDefinition = json_decode($project->project_definition, true);
            $projectName = $projectDefinition['project']['name'];
            $projectId = $project->project_id;
            $forms = $projectDefinition['project']['forms'];

            foreach ($forms as $formKey => $form) {

                $inputs = $form['inputs'];

                foreach ($inputs as $inputKey => $input) {

                    if ($input['type'] === 'time' && $input['uniqueness'] !== 'none') {
                        array_push($projectsWithTimeUniqueness, $projectName);
                        array_push($projectIds, $projectId);
                        break 2;
                    }


                    if ($input['type'] === 'branch') {
                        foreach ($input['branch'] as $branchInput) {

                            if ($branchInput['type'] === 'time' && $branchInput['uniqueness'] !== 'none') {
                                array_push($projectsWithTimeUniqueness, $projectName);
                                array_push($projectIds, $projectId);
                                break 3;
                            }


                            if ($input['type'] === 'group') {
                                foreach ($input['group'] as $key => $groupInput) {

                                    if ($groupInput['type'] === 'time' && $groupInput['uniqueness'] !== 'none') {
                                        array_push($projectsWithTimeUniqueness, $projectName);
                                        array_push($projectIds, $projectId);
                                        break 4;
                                    }
                                }
                            }
                        }
                    }

                    if ($input['type'] === 'group') {
                        foreach ($input['group'] as $groupInput) {
                            if ($groupInput['type'] === 'time' && $groupInput['uniqueness'] !== 'none') {
                                array_push($projectsWithTimeUniqueness, $projectName);
                                array_push($projectIds, $projectId);
                                break 3;
                            }
                        }
                    }
                }
            }
        }

        //sort array
        return ['projects' => $projectsWithTimeUniqueness, 'project_ids' => $projectIds];
    }

    public function findProjectsStorageUsedDefault()
    {
        return $this->findProjectsStorageUsed(10);
    }

    public function findProjectsStorageUsed($threshold)
    {
        $start = Carbon::now()->getTimestamp();
        $table = 'storage_stats_remote';
        $thresholdInt = (int)$threshold;
        $costXGB = floatval(config('epicollect.setup.cost_x_gb'));

        $projectsOver = DB::table($table)
            ->where('entries', '>', $thresholdInt)
            ->orderBy('overall_bytes', 'DESC');
        // ->get();

        $projectsUnder = DB::table($table)
            ->where('entries', '<=', $thresholdInt)
            ->orderBy('overall_bytes', 'DESC');
        //  ->get();


        $csvFilenameOver = 'storage-over.csv';
        $csvFilenameUnder = 'storage-under.csv';
        $csvFilenameOverall = 'storage-overall.csv';

        //create empty csv files in the temp/subset/{$project_ref} folder
        Storage::disk('debug')->put(
            $csvFilenameOver,
            ''
        );
        Storage::disk('debug')->put(
            $csvFilenameUnder,
            ''
        );

        Storage::disk('debug')->put(
            $csvFilenameOverall,
            ''
        );

        //get handle of empty file just created
        $CSVfilepathOver = Storage::disk('debug')
                ->getAdapter()
                ->getPathPrefix()
            . $csvFilenameOver;

        $CSVfilepathUnder = Storage::disk('debug')
                ->getAdapter()
                ->getPathPrefix()
            . $csvFilenameUnder;

        $CSVfilepathOverall = Storage::disk('debug')
                ->getAdapter()
                ->getPathPrefix()
            . $csvFilenameOverall;

        //write to file one row at a time to keep memory usage low
        $csvOver = Writer::createFromPath($CSVfilepathOver, 'w+');
        $csvUnder = Writer::createFromPath($CSVfilepathUnder, 'w+');
        $csvOverall = Writer::createFromPath($CSVfilepathOverall, 'w+');

        $csvOver->insertOne([
            'id',
            'ref',
            'name',
            'files',
            'entries',
            'latest entry uploaded',
            'branches',
            'latest branch uploaded',
            'storage (total)',
            'storage (audio)',
            'storage (photo)',
            'storage (video)',
            'storage (raw total bytes)',
            'cost ($0.10 x GB )',
        ]);

        $csvUnder->insertOne([
            'id',
            'ref',
            'name',
            'files',
            'entries',
            'latest entry uploaded',
            'branches',
            'latest branch uploaded',
            'storage (total)',
            'storage (audio)',
            'storage (photo)',
            'storage (video)',
            'storage (raw total bytes)',
            'cost ($0.10 x GB )',
        ]);


        $csvOverall->insertOne([
            'Total',
            'Under',
            'Over',
            'Cost Under',
            'Cost Over',
            'Threshold'
        ]);

        $projectsOver->chunk(5000, function ($chunkedProjects) use ($csvOver, $costXGB) {
            foreach ($chunkedProjects as $chunkedProject) {
                $csvOver->insertOne([
                    $chunkedProject->project_id,
                    $chunkedProject->project_ref,
                    $chunkedProject->project_name,
                    $chunkedProject->files,
                    $chunkedProject->entries,
                    Carbon::parse($chunkedProject->last_entry_uploaded)->diffForHumans(),
                    $chunkedProject->branches,
                    $chunkedProject->branches > 0 ? Carbon::parse($chunkedProject->last_branch_uploaded)->diffForHumans() : '',
                    Common::formatBytes($chunkedProject->overall_bytes),
                    Common::formatBytes($chunkedProject->audio_bytes),
                    Common::formatBytes($chunkedProject->photo_bytes),
                    Common::formatBytes($chunkedProject->video_bytes),
                    $chunkedProject->overall_bytes,
                    '$' . round(((($chunkedProject->overall_bytes) / 1000000000)) * $costXGB, 3)
                ]);
            }
        });

        $projectsUnder->chunk(5000, function ($chunkedProjects) use ($csvUnder, $costXGB) {
            foreach ($chunkedProjects as $chunkedProject) {
                $csvUnder->insertOne([
                    $chunkedProject->project_id,
                    $chunkedProject->project_ref,
                    $chunkedProject->project_name,
                    $chunkedProject->files,
                    $chunkedProject->entries,
                    Carbon::parse($chunkedProject->last_entry_uploaded)->diffForHumans(),
                    $chunkedProject->branches,
                    $chunkedProject->branches > 0 ? Carbon::parse($chunkedProject->last_branch_uploaded)->diffForHumans() : '',
                    Common::formatBytes($chunkedProject->overall_bytes),
                    Common::formatBytes($chunkedProject->audio_bytes),
                    Common::formatBytes($chunkedProject->photo_bytes),
                    Common::formatBytes($chunkedProject->video_bytes),
                    $chunkedProject->overall_bytes,
                    '$' . round(((($chunkedProject->overall_bytes) / 1000000000)) * $costXGB, 3)
                ]);
            }
        });

        $bytesOver = DB::table($table)
            ->where('entries', '>', $thresholdInt)
            ->sum('overall_bytes');

        $bytesUnder = DB::table($table)
            ->where('entries', '<=', $thresholdInt)
            ->sum('overall_bytes');

        $bytesOver = (int)$bytesOver;
        $bytesUnder = (int)$bytesUnder;

        $costUnder = '$' . round(((($bytesUnder) / 1000000000)) * $costXGB, 3);
        $costOver = '$' . round(((($bytesOver) / 1000000000)) * $costXGB, 3);
        $csvOverall->insertOne([
            Common::formatBytes($bytesOver + $bytesUnder),
            Common::formatBytes($bytesUnder),
            Common::formatBytes($bytesOver),
            $costUnder,
            $costOver,
            $threshold
        ]);


        $duration = Carbon::now()->getTimestamp() - $start;
        $duration = $duration > 0 ? $duration : 1;

        // return [
        //     'executed in' => CarbonInterval::seconds($duration)->cascade()->forHumans(),
        // ];


        $filepath = $this->createZipArchive();

        return response()->download($filepath)->deleteFileAfterSend(true);
    }

    public function findProjectsStorageUsedTableDefault()
    {
        return $this->findProjectsStorageUsedTable(null);
    }

    public function findProjectsStorageUsedTable($year)
    {
        $start = Carbon::now()->getTimestamp();
        //todo: validate year
        if ($year) {
            $projectIDs = DB::table('projects')
                ->whereYear('created_at', $year)
                ->pluck('id')
                ->toArray();
            $entries = DB::table('entries')
                ->join('project_stats', 'entries.project_id', '=', 'project_stats.project_id')
                ->whereIn('entries.project_id', $projectIDs)
                ->select('entries.project_id', 'entries.branch_counts', DB::raw('MAX(entries.uploaded_at) as latest_entry'), 'project_stats.total_entries')
                ->groupBy('entries.project_id')
                ->orderBy('project_stats.total_entries', 'DESC');
        } else {
            $entries = DB::table('entries')
                ->join('project_stats', 'entries.project_id', '=', 'project_stats.project_id')
                ->select('entries.project_id', 'entries.branch_counts', DB::raw('MAX(entries.uploaded_at) as latest_entry'), 'project_stats.total_entries')
                ->groupBy('entries.project_id')
                ->orderBy('project_stats.total_entries', 'DESC');
        }

        $projectsMined = 0;
        $projectsUpdated = 0;
        $projectsSkipped = 0;
        $entries->chunk(5000, function ($chunkedEntries) use (&$projectsMined, &$projectsUpdated, &$projectsSkipped) {
            foreach ($chunkedEntries as $chunkedEntry) {

                $projectsMined++;
                //imp: json_decode($i, true) to get array not stdClass
                $createStorageRow = false;
                $updateStorageRow = false;
                $branchCounts = json_decode($chunkedEntry->branch_counts, true);
                $branchLatest = '';
                $files = 0;
                //skip empty arrays (i.e. no branches)
                if (is_array($branchCounts)) {
                    if (sizeOf($branchCounts) > 0) {
                        //skip if no branch entries were collected
                        if (array_sum($branchCounts) > 0) {
                            //get latest branch entry
                            $branchLatest = DB::table('branch_entries')
                                ->select(DB::raw('MAX(uploaded_at) as latest_branch_entry'))
                                ->where('project_id', '=', $chunkedEntry->project_id)->value('latest_branch_entry');
                        }
                    }
                }
                //get project name and ref (single db query to use less RAM)
                $project = DB::table('projects')->where('id', '=', $chunkedEntry->project_id)->pluck('name', 'ref')->toArray();
                $projectRef = array_keys($project)[0];
                $projectName = $project[$projectRef];
                $drivers = [
                    'entry_original',
                    'entry_thumb',
                    'project_thumb',
                    'project_mobile_logo',
                    'video',
                    'audio'
                ];

                //check if the storage stats for this project are already up-to-date
                if (!StorageStats::where('project_id', $chunkedEntry->project_id)->exists()) {
                    $projectsUpdated++;
                    $createStorageRow = true;
                } else {
                    $projectStorage = StorageStats::where('project_id', $chunkedEntry->project_id)->first();
                    //does it need to be updated?
                    if ($projectStorage->entries !== $chunkedEntry->total_entries) {
                        if (!$updateStorageRow) {
                            $updateStorageRow = true;
                            $projectsUpdated++;
                        }
                    }

                    if (is_array($branchCounts)) {
                        if (sizeOf($branchCounts) > 0) {
                            //skip if no branch entries were collected
                            if (array_sum($branchCounts) !== $projectStorage->branches) {
                                if (!$updateStorageRow) {
                                    $updateStorageRow = true;
                                    $projectsUpdated++;
                                }
                            }
                        }
                    }

                    if ($projectStorage->last_entry_uploaded !== $chunkedEntry->latest_entry) {
                        if (!$updateStorageRow) {
                            $updateStorageRow = true;
                            $projectsUpdated++;
                        }
                    }

                    if ($branchLatest) {
                        if ($projectStorage->last_branch_uploaded !== $branchLatest) {
                            if (!$updateStorageRow) {
                                $updateStorageRow = true;
                                $projectsUpdated++;
                            }
                        }
                    }
                }

                // Loop each driver
                if ($createStorageRow || $updateStorageRow) {
                    foreach ($drivers as $driver) {
                        // Get disk, path prefix and all directories for this driver
                        $disk = Storage::disk($driver);
                        // Loop each media directory
                        $data = [];
                        $size = 0;

                        //still the fastest way
                        foreach ($disk->files($projectRef) as $file) {
                            //size in bytes
                            $size += Storage::disk($driver)->size($file);
                            $files++;
                        }
                        try {
                            $data = array_add($data, $projectRef, $size);
                        } catch (Exception $e) {
                            $data = array_add($data, $projectRef, $size);
                        }

                        foreach ($data as $ref => $size) {
                            try {
                                if (!array_key_exists('storage', $project)) {
                                    $project['storage'] = 0;
                                }
                                $project['storage'] += $size;
                            } catch (Exception $e) {
                                // Log::info('No media files for ' . $projectName,  ['error' => $e->getMessage()]);
                            }

                            switch ($driver) {
                                case 'entry_original':
                                    $project['photo'] = $data[$ref];
                                    break;
                                case 'entry_thumb':
                                    $project['photo'] += $data[$ref];
                                    break;
                                case 'project_thumb':
                                    $project['photo'] += $data[$ref];
                                    break;
                                case 'project_mobile_logo':
                                    $project['photo'] += $data[$ref];
                                    break;
                                case 'audio':
                                    $project['audio'] = $data[$ref];
                                    break;
                                case 'video':
                                    $project['video'] = $data[$ref];
                                    break;
                            }
                        }
                    }

                    $projectStorage = StorageStats::updateOrCreate(
                        [
                            'project_id' => $chunkedEntry->project_id,
                            'project_ref' => $projectRef
                        ],
                        [
                            'project_name' => $projectName,
                            'files' => $files,
                            'entries' => $chunkedEntry->total_entries,
                            'branches' => is_array($branchCounts) ? array_sum($branchCounts) : 0,
                            'last_entry_uploaded' => $chunkedEntry->latest_entry ?? null,
                            'last_branch_uploaded' => $branchLatest ?? null,
                            'audio_bytes' => $project['audio'] ?? 0,
                            'photo_bytes' => $project['photo'] ?? 0,
                            'video_bytes' => $project['video'] ?? 0,
                            'overall_bytes' => $project['storage'] ?? 0

                        ]
                    );

                    // $projectStorage->project_id = $chunkedEntry->project_id;
                    // $projectStorage->project_ref = $projectRef;
                    // $projectStorage->project_name = $projectName;
                    // $projectStorage->files = $files;
                    // $projectStorage->entries = $chunkedEntry->total_entries;
                    // $projectStorage->branches = is_array($branchCounts) ? array_sum($branchCounts) : 0;
                    // $projectStorage->last_entry_uploaded = $chunkedEntry->latest_entry ?? null;
                    // $projectStorage->last_branch_uploaded =  $branchLatest ?? null;
                    // $projectStorage->audio_bytes = $project['audio'] ?? 0;
                    // $projectStorage->photo_bytes = $project['photo'] ?? 0;
                    // $projectStorage->video_bytes = $project['video'] ?? 0;
                    // $projectStorage->overall_bytes = $project['storage'] ?? 0;
                    // $projectStorage->save();
                }

                if (!$createStorageRow && !$updateStorageRow) {
                    $projectsSkipped++;
                }
            }
        });

        $duration = Carbon::now()->getTimestamp() - $start;
        $duration = $duration > 0 ? $duration : 1;

        return [
            'executed in' => CarbonInterval::seconds($duration)->cascade()->forHumans(),
            'year' => $year ?? 'lifetime',
            'mined' => $projectsMined,
            'updated' => $projectsUpdated,
            'skipped' => $projectsSkipped
        ];
    }

    private function createZipArchive()
    {
        $zipFilename = 'storage-info.zip';
        $zip = new ZipArchive();
        $pathDebugDir = Storage::disk('debug')
            ->getAdapter()
            ->getPathPrefix();
        $zipFilepath = $pathDebugDir . $zipFilename;

        //create empty zip file
        $zip->open($zipFilepath, \ZipArchive::CREATE);

        foreach (Storage::disk('debug')->files() as $file) {
            //filter by .csv only
            if (pathinfo($file, PATHINFO_EXTENSION) == 'csv') {
                $zip->addFile($pathDebugDir . pathinfo($file, PATHINFO_BASENAME), pathinfo($file, PATHINFO_BASENAME));
            }
        }

        $zip->close();

        //delete temp csv files
        foreach (Storage::disk('debug')->files() as $file) {
            //filter by .csv only
            if (pathinfo($file, PATHINFO_EXTENSION) == 'csv') {
                File::delete($pathDebugDir . pathinfo($file, PATHINFO_BASENAME));
            }
        }

        return $zipFilepath;
    }

    function dirSize($directory)
    {
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    private function GetDirSizeBytes($absolutePath)
    {
        $res = exec("du -b -s $absolutePath");
        if (preg_match("/\d+/", $res, $bytes)) {
            return $bytes[0];
        }
        return -1;
    }
}
