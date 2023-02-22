<?php

namespace ec5\Http\Controllers\Web\Admin\Tools;

use ec5\Http\Controllers\Controller;

use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\Entry;
use Symfony\Component\HttpFoundation\Request;
use DB;
use Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use ec5\Libraries\DirectoryGenerator\DirectoryGenerator;
use ec5\Libraries\Utilities\Common;
use Exception;
use ZipArchive;
use Cookie;
use Illuminate\Support\Str;
use League\Csv\Writer;
use SplTempFileObject;
use Auth;
use Ramsey\Uuid\Uuid;
use Log;

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
        $this->findProjectsStorageUsed(10);
    }

    public function findProjectsStorageUsed($threshold)
    {
        \LOG::info('Usage: ' . Common::formatBytes(memory_get_usage()));
        \LOG::info('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));
        $thresholdInt = (int)$threshold;
        $costXGB = floatval(env('COST_X_GB', 0.10));

        $projectIDsOver = DB::table('project_stats')
            ->where('total_entries', '>', $thresholdInt)
            ->orderBy('total_entries', 'DESC')
            ->pluck('project_id')
            ->toArray();

        $projectIDsUnder = DB::table('project_stats')
            ->where('total_entries', '<=', $thresholdInt)
            ->orderBy('total_entries', 'DESC')
            ->pluck('project_id')
            ->toArray();

        //dd($projectIDsOver,  $projectIDsUnder);

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

        $storageOverall = [
            'over' => 0,
            'under' => 0
        ];

        $csvOverall->insertOne([
            'Total',
            'Under',
            'Over',
            'Cost Under',
            'Cost Over',
            'Threshold'
        ]);

        $entriesOver = DB::table('entries')
            ->join('project_stats', 'entries.project_id', '=', 'project_stats.project_id')
            ->whereIn('entries.project_id', $projectIDsOver)
            ->select('entries.project_id', 'entries.branch_counts', DB::raw('MAX(entries.uploaded_at) as latest_entry'), 'project_stats.total_entries')
            ->groupBy('entries.project_id')
            ->orderBy('project_stats.total_entries', 'DESC');

        $entriesUnder = DB::table('entries')
            ->join('project_stats', 'entries.project_id', '=', 'project_stats.project_id')
            ->whereIn('entries.project_id', $projectIDsUnder)
            ->select('entries.project_id', 'entries.branch_counts', DB::raw('MAX(entries.uploaded_at) as latest_entry'), 'project_stats.total_entries')
            ->distinct('entries.project_id')
            ->groupBy('entries.project_id')
            ->orderBy('project_stats.total_entries', 'DESC');

        //dd($projectIDsOver,  $projectIDsUnder, $entriesOver->get(),  $entriesUnder->get());

        $entriesOver->chunk(500, function ($chunkedEntries) use ($costXGB, $csvOver, &$storageOverall) {
            foreach ($chunkedEntries as $chunkedEntry) {

                //imp: json_decode($i, true) to get array not stdClass
                $branchCounts = json_decode($chunkedEntry->branch_counts, true);
                $branchLatest = '';
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
                $drivers = ['entry_original', 'audio', 'video'];
                $storage = [];

                // Log::info('Project name' .  $projectName);
                // Log::info('Project ref' .  $projectRef);

                // Loop each driver
                foreach ($drivers as $driver) {
                    // Get disk, path prefix and all directories for this driver
                    $disk = Storage::disk($driver);
                    //  $projectRefs = $this->directoryGenerator($disk);
                    // Loop each media directory
                    $data = [];
                    //  foreach ($projectRefs as $projectRef) {

                    $size = 0;

                    // Log::info('Checking driver ->' . $driver . ' for  ' . $projectRef);
                    foreach ($disk->files($projectRef) as $file) {
                        //size in bytes
                        Log::info('Found file ->', ['file' => $file]);
                        $size += Storage::disk($driver)->size($file);
                    }
                    $storageOverall['over'] += $size;
                    Log::info('Storage for project so far -> ' . $size);
                    Log::info('Storage overall so far -> ' .  $storageOverall['over']);

                    try {
                        $data = array_add($data, $projectRef, $size);
                    } catch (Exception $e) {
                        Log::info('array_add() ' . $projectName,  ['error' => $e->getMessage()]);
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
                            case 'audio':
                                $project['audio'] = $data[$ref];
                                break;
                            case 'video':
                                $project['video'] = $data[$ref];
                                break;
                        }
                    }

                    switch ($driver) {
                        case 'entry_original':
                            $storage = array_add($storage, 'photo',  $data);
                            break;
                        case 'audio':
                            $storage = array_add($storage, $driver,  $data);
                            break;
                        case 'video':
                            $storage = array_add($storage, $driver,  $data);
                            break;
                    }
                }

                $csvOver->insertOne([
                    $chunkedEntry->project_id,
                    $ref,
                    $project[$ref], //project name
                    $chunkedEntry->total_entries,
                    Carbon::parse($chunkedEntry->latest_entry)->diffForHumans(),
                    is_array($branchCounts) ? array_sum($branchCounts) : 0,
                    Carbon::parse($branchLatest)->diffForHumans(),
                    Common::formatBytes($project['storage']),
                    Common::formatBytes($project['audio']),
                    Common::formatBytes($project['photo']),
                    Common::formatBytes($project['video']),
                    $project['storage'],
                    '$' . round(((($project['storage']) / 1000000000)) * $costXGB, 3)
                ]);
            }
        });

        //storage for projects under the threshold
        $entriesUnder->chunk(500, function ($chunkedEntries) use ($costXGB, $csvUnder,  &$storageOverall) {
            foreach ($chunkedEntries as $chunkedEntry) {

                //imp: json_decode($i, true) to get array not stdClass
                $branchCounts = json_decode($chunkedEntry->branch_counts, true);
                $branchLatest = '';
                //skip empty arrays (i.e. no branches)
                if (is_array($branchCounts)) {
                    if (sizeOf($branchCounts) > 0) {
                        //skip if no branch entries were collected
                        if (array_sum($branchCounts) > 0) {
                            //get latest branch entry
                            $branchLatest = DB::table('branch_entries')
                                ->select(DB::raw('MAX(uploaded_at) as latest_branch_entry'))
                                ->where('project_id', '=', $chunkedEntry->project_id)->value('latest_branch_entry');
                            // echo $branchLatest;
                            // echo '<br/>';
                            // echo '<br/>';
                            // echo 'Project ID:' . $chunkedEntry->project_id . ' branches:' . array_sum($branchCounts);
                            // echo '<br/>';
                        }
                    }
                }
                //get project name and ref (single db query to use less RAM)
                $project = DB::table('projects')->where('id', '=', $chunkedEntry->project_id)->pluck('name', 'ref')->toArray();
                $projectRef = array_keys($project)[0];
                $projectName = $project[$projectRef];
                $drivers = ['entry_original', 'audio', 'video'];
                $storage = [];


                // Loop each driver
                foreach ($drivers as $driver) {
                    // Get disk, path prefix and all directories for this driver
                    $disk = Storage::disk($driver);
                    //  $projectRefs = $this->directoryGenerator($disk);
                    // Loop each media directory
                    $data = [];
                    //  foreach ($projectRefs as $projectRef) {

                    $size = 0;

                    //   Log::info('Checking driver ->' . $driver . ' for  ' . $projectRef);
                    foreach ($disk->files($projectRef) as $file) {
                        //size in bytes
                        Log::info('Found file ->', ['file' => $file]);
                        $size += Storage::disk($driver)->size($file);
                    }

                    $storageOverall['under'] += $size;

                    Log::info('Storage for project so far -> ' . $size);
                    Log::info('Storage overall so far -> ' . $storageOverall['under']);

                    try {
                        $data = array_add($data, $projectRef, $size);
                    } catch (Exception $e) {
                        //  Log::info('array_add() ' . $projectName,  ['error' => $e->getMessage()]);
                        $data = array_add($data, $projectRef, $size);
                    }

                    foreach ($data as $ref => $size) {
                        try {
                            if (!array_key_exists('storage', $project)) {
                                $project['storage'] = 0;
                            }
                            $project['storage'] += $size;
                        } catch (Exception $e) {
                            Log::info('No media files for ' . $projectName,  ['error' => $e->getMessage()]);
                        }
                        switch ($driver) {
                            case 'entry_original':
                                $project['photo'] = $data[$ref];
                                break;
                            case 'audio':
                                $project['audio'] = $data[$ref];
                                break;
                            case 'video':
                                $project['video'] = $data[$ref];
                                break;
                        }
                    }

                    switch ($driver) {
                        case 'entry_original':
                            $storage = array_add($storage, 'photo',  $data);
                            break;
                        case 'audio':
                            $storage = array_add($storage, $driver,  $data);
                            break;
                        case 'video':
                            $storage = array_add($storage, $driver,  $data);
                            break;
                    }
                }

                $csvUnder->insertOne([
                    $chunkedEntry->project_id,
                    $ref,
                    $project[$ref], //project name
                    $chunkedEntry->total_entries,
                    Carbon::parse($chunkedEntry->latest_entry)->diffForHumans(),
                    is_array($branchCounts) ? array_sum($branchCounts) : 0,
                    Carbon::parse($branchLatest)->diffForHumans(),
                    Common::formatBytes($project['storage']),
                    Common::formatBytes($project['audio']),
                    Common::formatBytes($project['photo']),
                    Common::formatBytes($project['video']),
                    $project['storage'],
                    '$' . round(((($project['storage']) / 1000000000)) * $costXGB, 3)
                ]);
            }
        });

        $costUnder =  '$' . round(((($storageOverall['under']) / 1000000000)) * $costXGB, 3);
        $costOver =  '$' . round(((($storageOverall['over']) / 1000000000)) * $costXGB, 3);
        $csvOverall->insertOne([
            Common::formatBytes($storageOverall['under'] + $storageOverall['over']),
            Common::formatBytes($storageOverall['under']),
            Common::formatBytes($storageOverall['over']),
            $costUnder,
            $costOver,
            $threshold
        ]);


        \LOG::info('Usage: ' . Common::formatBytes(memory_get_usage()));
        \LOG::info('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));


        $filepath = $this->createZipArchive();

        return response()->download($filepath)->deleteFileAfterSend(true);
    }

    private function createZipArchive()
    {
        $zipFilename =  'storage-info.zip';
        $zip = new ZipArchive();
        $pathDebugDir = Storage::disk('debug')
            ->getAdapter()
            ->getPathPrefix();
        $zipFilepath =  $pathDebugDir . $zipFilename;

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
}
