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

    public function findProjectsStorageUsed()
    {

        \LOG::info('Usage: ' . Common::formatBytes(memory_get_usage()));
        \LOG::info('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));
        $threshold = 10;
        $filename = 'storage-info.zip';
        $currentProjectRef = '';
        $projectIDsOver10 = DB::table('project_stats')
            ->where('total_entries', '>', $threshold)
            ->orderBy('total_entries', 'DESC')
            // ->take(100)
            ->pluck('total_entries', 'project_id')->toArray();

        $projectIDsUnder10 = DB::table('project_stats')
            ->where('total_entries', '<=', $threshold)
            ->orderBy('total_entries', 'DESC')
            ->pluck('total_entries', 'project_id')
            ->toArray();

        $csvFilename = 'storage-info.csv';
        $zipFilename =  'storage-info.zip';

        $zip = new ZipArchive();

        //create an empty csv file in the temp/subset/{$project_ref} folder
        Storage::disk('debug')->put(
            $csvFilename,
            ''
        );

        //get handle of empty file just created
        $CSVfilepath = Storage::disk('debug')
            ->getAdapter()
            ->getPathPrefix()
            . $csvFilename;

        $zipFilepath = Storage::disk('debug')
            ->getAdapter()
            ->getPathPrefix()
            . $zipFilename;

        //create empty zip file
        $zip->open($zipFilepath, \ZipArchive::CREATE);

        //write to file one row at a time to keep memory usage low
        $csv = Writer::createFromPath($CSVfilepath, 'w+');

        $csv->insertOne([
            'id',
            'name',
            'entries',
            'latest entry uploaded',
            'branches',
            'latest branch uploaded',
            'storage (total)',
            'storage (audio)',
            'storage (photo)',
            'storage (video)'
        ]);


        \LOG::info('Usage: ' . Common::formatBytes(memory_get_usage()));
        \LOG::info('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));

        $entries = DB::table('entries')
            ->join('project_stats', 'entries.project_id', '=', 'project_stats.project_id')
            ->select('entries.project_id', 'entries.branch_counts', DB::raw('MAX(entries.uploaded_at) as latest_entry'), 'project_stats.total_entries')
            ->groupBy('entries.project_id')
            ->whereIn('entries.project_id', $projectIDsOver10)
            ->orderBy('project_stats.total_entries', 'DESC');

        $entries->chunk(500, function ($chunkedEntries) use ($csv,  $currentProjectRef) {
            foreach ($chunkedEntries as $chunkedEntry) {
                //imp: json_decode($i, true) to get array not stdClass
                $branchCounts = json_decode($chunkedEntry->branch_counts, true);
                $branchLatest = '';
                //skip empty arrays (i.e. no branches)
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
                //get project name and ref (single db query to use less RAM)
                $project = DB::table('projects')->where('id', '=', $chunkedEntry->project_id)->pluck('name', 'ref')->toArray();
                $projectRef = array_keys($project)[0];
                $projectName = $project[$projectRef];
                //echo print_r($project);
                // echo '<br/>';



                // dd(array_keys($project)[0]);

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
                    foreach (Storage::disk($driver)->files($projectRef) as $file) {
                        //size in bytes
                        $size += Storage::disk($driver)->size($file);
                    }

                    try {
                        $data = array_add($data, $projectRef . ' - ' . $project[$projectRef], $size);
                    } catch (Exception $e) {
                        Log::info('Project ref skipped',  ['error' => $e->getMessage()]);
                        $data = array_add($data, $projectRef, $size);
                    }
                    //   }

                    //sort by storage in bytes, desc
                    uasort($data, function ($a, $b) {
                        if ($a == $b) {
                            return 0;
                        }
                        return ($a > $b) ? -1 : 1;
                    });

                    foreach ($data as $ref => $size) {
                        try {
                            $project[$ref]['storage'] += $size;
                        } catch (Exception $e) {
                            Log::info('Project ref skipped',  ['error' => $e->getMessage()]);
                        }
                        switch ($driver) {
                            case 'entry_original':
                                $project[$ref]['photo'] = $data[$ref];
                                break;
                            case 'audio':
                                $project[$ref]['audio'] = $data[$ref];
                                break;
                            case 'video':
                                $project[$ref]['video'] = $data[$ref];
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


                // $csv->insertOne([
                //     $chunkedEntry['id'],
                //    // $project['name'],
                //     $chunkedEntry['total_entries'],
                //     $chunkedEntry['latest_entry'],
                //     array_sum($branchCounts),
                //     $branchLatest,
                //     Common::formatBytes($project['storage']),
                //     Common::formatBytes($project['audio']),
                //     Common::formatBytes($project['photo']),
                //     Common::formatBytes($project['video']),
                // ]);
                echo 'Begin';



                dd($chunkedEntry, $project, $storage);
                echo 'End';
            }
        });

        \LOG::info('Usage: ' . Common::formatBytes(memory_get_usage()));
        \LOG::info('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));

        // dd($projectsOverThreshold);

        // dd($entries);


        $projectsUnderThreshold = DB::select('select temp.project_id, temp.name, temp.ref, temp.latest_entry, project_stats.total_entries, temp.branch_counts from (SELECT entries.project_id, ANY_VALUE(entries.branch_counts) as branch_counts,max(entries.created_at) as latest_entry, projects.ref as ref, projects.name as name from entries, projects where entries.project_id IN (SELECT project_stats.project_id as project_id from project_stats where project_stats.total_entries<=' . $threshold . ') AND entries.project_id=projects.id group by entries.project_id) as temp INNER JOIN project_stats ON temp.project_id=project_stats.project_id ORDER BY project_stats.total_entries DESC');
        $projectsOverall = array_merge($projectsOverThreshold, $projectsUnderThreshold);
        $branchEntries =  collect(DB::select('select projects.id, projects.ref, max(branch_entries.uploaded_at) as latest_branch_entry from projects, branch_entries where projects.id=branch_entries.project_id group by projects.ref, projects.id'))->keyBy('ref');


        \LOG::info('Usage: ' . Common::formatBytes(memory_get_usage()));
        \LOG::info('Peak Usage: ' . Common::formatBytes(memory_get_peak_usage()));
        dd();



        $mapProjectsToRefs = [];

        foreach ($projectsOverall as $project) {
            $mapProjectsToRefs[$project->ref] = [
                'id' => $project->project_id,
                'name' => $project->name,
                'entries' => $project->total_entries,
                'branch_entries' => $branchEntries[$project->ref]->branch_count ?? 0,
                'latest_entry' => Carbon::parse($project->latest_entry)->diffForHumans(),
                'latest_branch_entry' => '',
                'storage' => 0,
                'audio' => 0,
                'photo' => 0,
                'video' => 0,
            ];

            if ($mapProjectsToRefs[$project->ref]['branch_entries'] > 0) {
                $lastBranchEntryForHumans = Carbon::parse($branchEntries[$project->ref]->latest_branch_entry)->diffForHumans();
                $mapProjectsToRefs[$project->ref]['latest_branch_entry'] =  $lastBranchEntryForHumans;
            }

            //'latest_branch_entry' => Carbon::parse($project->latest_entry)->diffForHumans(),
        }


        $drivers = ['entry_original', 'audio', 'video'];
        $storage = [];
        // Loop each driver
        foreach ($drivers as $driver) {

            // Get disk, path prefix and all directories for this driver
            $disk = Storage::disk($driver);
            $projectRefs = $this->directoryGenerator($disk);

            // Loop each media directory
            $data = [];
            foreach ($projectRefs as $projectRef) {
                $size = 0;
                foreach (Storage::disk($driver)->files($projectRef) as $file) {
                    //size in bytes
                    $size += Storage::disk($driver)->size($file);
                }

                try {
                    $data = array_add($data, $projectRef . ' - ' . $mapProjectsToRefs[$projectRef], $size);
                } catch (Exception $e) {
                    Log::info('Project ref skipped',  ['error' => $e->getMessage()]);
                    $data = array_add($data, $projectRef, $size);
                }
            }

            //sort by storage in bytes, desc
            uasort($data, function ($a, $b) {
                if ($a == $b) {
                    return 0;
                }
                return ($a > $b) ? -1 : 1;
            });

            foreach ($data as $ref => $size) {
                try {
                    $mapProjectsToRefs[$ref]['storage'] += $size;
                } catch (Exception $e) {
                    Log::info('Project ref skipped',  ['error' => $e->getMessage()]);
                }
                switch ($driver) {
                    case 'entry_original':
                        $mapProjectsToRefs[$ref]['photo'] = $data[$ref];
                        break;
                    case 'audio':
                        $mapProjectsToRefs[$ref]['audio'] = $data[$ref];
                        break;
                    case 'video':
                        $mapProjectsToRefs[$ref]['video'] = $data[$ref];
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
        //sort by total storage, desc
        uasort($mapProjectsToRefs, function ($a, $b) {
            if ($a['storage'] == $b['storage']) return 0;
            return ($a['storage'] > $b['storage']) ? -1 : 1;
        });

        $storageUnderThreshold = 0;
        foreach ($projectsUnderThreshold as $project) {
            if (isset($mapProjectsToRefs[$project->ref])) {
                $storageUnderThreshold += $mapProjectsToRefs[$project->ref]['storage'];
            }
        }
        $storageOverThreshold = 0;
        foreach ($projectsOverThreshold as $project) {
            if (isset($mapProjectsToRefs[$project->ref])) {
                $storageOverThreshold += $mapProjectsToRefs[$project->ref]['storage'];
            }
        }


        $filepath = $this->writeFileCsvZipped($mapProjectsToRefs, $storageUnderThreshold, $storageOverThreshold);

        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
    }

    private function writeFileCsvZipped($projects, $storageUnderThreshold, $storageOverThreshold)
    {
        $csvFilename = 'storage-info.csv';
        $zipFilename =  'storage-info.zip';

        $zip = new ZipArchive();

        //create an empty csv file in the temp/subset/{$project_ref} folder
        Storage::disk('debug')->put(
            $csvFilename,
            ''
        );

        //get handle of empty file just created
        $CSVfilepath = Storage::disk('debug')
            ->getAdapter()
            ->getPathPrefix()
            . $csvFilename;

        $zipFilepath = Storage::disk('debug')
            ->getAdapter()
            ->getPathPrefix()
            . $zipFilename;

        //create empty zip file
        $zip->open($zipFilepath, \ZipArchive::CREATE);

        //write to file one row at a time to keep memory usage low
        $csv = Writer::createFromPath($CSVfilepath, 'w+');

        try {
            //write headers
            $csv->insertOne([
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'total for projects over 10 entries',
                'total for projects under 10 entries'
            ]);
            $csv->insertOne([
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                Common::formatBytes($storageUnderThreshold, 0),
                Common::formatBytes($storageOverThreshold, 0)
            ]);
            $csv->insertOne([
                'id',
                'name',
                'entries',
                'latest entry uploaded',
                'branches',
                'latest branch uploaded',
                'storage (total)',
                'storage (audio)',
                'storage (photo)',
                'storage (video)'
            ]);

            foreach ($projects as $project) {
                $csv->insertOne([
                    $project['id'],
                    $project['name'],
                    $project['entries'],
                    $project['latest_entry'],
                    $project['branch_entries'],
                    $project['latest_branch_entry'],
                    Common::formatBytes($project['storage']),
                    Common::formatBytes($project['audio']),
                    Common::formatBytes($project['photo']),
                    Common::formatBytes($project['video']),
                ]);
            }
        } catch (\Exception $e) {
            // Error writing to file
            Log::error($e->getMessage());
        }

        $zip->addFile($CSVfilepath,  $csvFilename);
        $zip->close();

        //delete temp csv file
        //Storage::disk('debug')->delete($csvFilename);

        return $zipFilepath;
    }
}
