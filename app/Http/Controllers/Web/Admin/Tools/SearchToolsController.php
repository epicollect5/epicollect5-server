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

class SearchToolsController extends Controller
{
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
}
