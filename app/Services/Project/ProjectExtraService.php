<?php

namespace ec5\Services\Project;

class ProjectExtraService
{
    private $formRefs = [];
    private $inputsExtra = [];

    public function generateExtraStructure($projectDefinition): array
    {
        $project = $projectDefinition['project'];
        $forms = $projectDefinition['project']['forms'];
        $formsExtra = $this->buildFormsExtra($forms);


        return [
            'forms' => $formsExtra,
            'inputs' => $this->inputsExtra,
            'project' => $this->buildProjectExtra($project)
        ];
    }

    private function buildFormsExtra($forms): array
    {
        $formsExtra = [];
        $multipleChoiceQuestionTypes = array_keys(config('epicollect.strings.multiple_choice_question_types'));
        foreach ($forms as $index => $form) {
            $hasLocation = false;
            $locationInputs = [];
            $multipleChoiceInputs = [];
            $multipleChoiceInputRefsInOrder = [];
            $multipleChoiceBranchInputs = [];
            $multipleChoiceBranchInputRefsInOrder = [];
            $this->formRefs[] = $form['ref'];
            $inputs = $form['inputs'];
            $inputRefs = [];
            $branches = [];
            $groups = [];
            $groupInputRefs = [];

            foreach ($inputs as $input) {
                $this->inputsExtra[$input['ref']] = [
                    'data' => $input
                ];
                $inputRefs[] = $input['ref'];

                if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
                    $hasLocation = true;
                    $locationInputs[] = [
                        'question' => $input['question'],
                        'input_ref' => $input['ref'],
                        'branch_ref' => null
                    ];
                }

                if (in_array($input['type'], $multipleChoiceQuestionTypes)) {
                    $multipleChoiceInputRefsInOrder[] = $input['ref'];
                    $multipleChoiceInputs[$input['ref']] = [
                        'question' => $input['question'],
                        'possible_answers' => $this->convertToHashMap($input['possible_answers'])
                    ];
                }

                //loop groups
                if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                    $groupInputs = $input['group'];
                    foreach ($groupInputs as $groupInput) {
                        $inputRefs[] = $groupInput['ref'];
                        $groupInputRefs[] = $groupInput['ref'];
                        $this->inputsExtra[$groupInput['ref']] = [
                            'data' => $groupInput
                        ];

                        if ($groupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                            $hasLocation = true;
                            $locationInputs[] = [
                                'question' => $groupInput['question'],
                                'input_ref' => $groupInput['ref'],
                                'branch_ref' => null
                            ];
                        }

                        if (in_array($groupInput['type'], $multipleChoiceQuestionTypes)) {
                            $multipleChoiceBranchInputRefsInOrder[] = $groupInput['ref'];
                            $multipleChoiceBranchInputs[$groupInput['ref']] = [
                                'question' => $groupInput['question'],
                                'possible_answers' => $this->convertToHashMap($groupInput['possible_answers'])
                            ];
                        }
                    }
                    $groups[$input['ref']] = $groupInputRefs;
                }


                //loop branches
                if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                    $branchInputRefs = [];
                    $branchInputs = $input['branch'];
                    foreach ($branchInputs as $branchInput) {

                        $branchInputRefs[] = $branchInput['ref'];
                        $inputRefs[] = $branchInput['ref'];
                        $this->inputsExtra[$branchInput['ref']] = [
                            'data' => $branchInput
                        ];

                        if ($branchInput['type'] === config('epicollect.strings.inputs_type.location')) {
                            $hasLocation = true;
                            $locationInputs[] = [
                                'question' => $branchInput['question'],
                                'input_ref' => $input['ref'],
                                'branch_ref' => $branchInput['ref']
                            ];
                        }

                        if (in_array($branchInput['type'], $multipleChoiceQuestionTypes)) {
                            $multipleChoiceBranchInputRefsInOrder[] = $branchInput['ref'];
                            $multipleChoiceBranchInputs[$branchInput['ref']] = [
                                'question' => $branchInput['question'],
                                'possible_answers' => $this->convertToHashMap($branchInput['possible_answers'])
                            ];
                        }

                        //loop groups
                        if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {
                            $branchGroupInputs = $branchInput['group'];
                            foreach ($branchGroupInputs as $branchGroupInput) {

                                $groupInputRefs[] = $branchGroupInput['ref'];
                                $inputRefs[] = $branchGroupInput['ref'];
                                $this->inputsExtra[$branchGroupInput['ref']] = [
                                    'data' => $branchGroupInput
                                ];

                                if ($branchGroupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                                    $hasLocation = true;
                                    $locationInputs[] = [
                                        'question' => $branchGroupInput['question'],
                                        'input_ref' => $branchGroupInput['ref'],
                                        'branch_ref' => $branchInput['ref']
                                    ];
                                }

                                if (in_array($branchGroupInput['type'], $multipleChoiceQuestionTypes)) {
                                    $multipleChoiceBranchInputRefsInOrder[] = $branchGroupInput['ref'];
                                    $multipleChoiceBranchInputs[$branchGroupInput['ref']] = [
                                        'question' => $branchGroupInput['question'],
                                        'possible_answers' => $this->convertToHashMap($branchGroupInput['possible_answers'])
                                    ];
                                }
                            }
                            $groups[$branchInput['ref']] = $groupInputRefs;
                        }

                    }
                    $branches[$input['ref']] = $branchInputRefs;
                }
            }

            $formsExtra[$this->formRefs[$index]] = [
                'group' => $groups,
                'lists' => [
                    'location_inputs' => $locationInputs,
                    'multiple_choice_inputs' => [
                        'form' => array_merge(['order' => $multipleChoiceInputRefsInOrder], $multipleChoiceInputs),
                        'branch' => array_merge(['order' => $multipleChoiceBranchInputRefsInOrder], $multipleChoiceBranchInputs)
                    ]
                ],
                'branch' => $branches,
                'inputs' => $inputRefs,
                'details' => [
                    'ref' => $this->formRefs[$index],
                    'name' => $form['name'],
                    'slug' => $form['slug'],
                    'type' => 'hierarchy',
                    'has_location' => $hasLocation
                ]
            ];
        }
        return $formsExtra;
    }

    private function buildProjectExtra($project): array
    {
        return [
            'forms' => $this->formRefs,
            'details' => [
                'ref' => $project['ref'],
                'name' => $project['name'],
                'slug' => $project['slug'],
                'access' => $project['access'],
                'status' => $project['status'],
                'logo_url' => $project['logo_url'],
                'visibility' => $project['visibility'],
                'small_description' => $project['small_description'],
                'description' => $project['description'],
                'category' => $project['category']
            ],
            'entries_limits' => $project['entries_limits']
        ];
    }


    private function convertToHashMap($possibleAnswers): array
    {
        $answersMap = [];
        foreach ($possibleAnswers as $answer) {
            $answersMap[$answer['answer_ref']] = $answer['answer'];
        }
        return $answersMap;
    }
}