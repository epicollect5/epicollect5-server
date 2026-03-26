<?php

namespace ec5\Services\Project;

class ProjectExtraService
{
    private array $formRefs = [];
    private array $inputsExtra = [];

    // State for the current form being processed
    private bool $hasLocation = false;
    private array $locationInputs = [];
    private array $multipleChoiceInputs = [];
    private array $multipleChoiceInputRefsInOrder = [];
    private array $multipleChoiceBranchInputs = [];
    private array $branches = [];
    private array $groups = [];

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
            // Reset state for each form
            $this->hasLocation = false;
            $this->locationInputs = [];
            $this->multipleChoiceInputs = [];
            $this->multipleChoiceInputRefsInOrder = [];
            $this->multipleChoiceBranchInputs = [];
            $this->branches = [];
            $this->groups = [];

            $this->formRefs[] = $form['ref'];
            $inputs = $form['inputs'];
            $inputRefs = [];

            foreach ($inputs as $input) {
                $inputRefs[] = $input['ref'];
                $this->processInput($input, $multipleChoiceQuestionTypes);
            }

            $formsExtra[$this->formRefs[$index]] = [
                'group' => $this->groups,
                'lists' => [
                    'location_inputs' => $this->locationInputs,
                    'multiple_choice_inputs' => [
                        'form' => array_merge(['order' => $this->multipleChoiceInputRefsInOrder], $this->multipleChoiceInputs),
                        'branch' => (object)$this->multipleChoiceBranchInputs
                    ]
                ],
                'branch' => $this->branches,
                'inputs' => $inputRefs,
                'details' => [
                    'ref' => $this->formRefs[$index],
                    'name' => $form['name'],
                    'slug' => $form['slug'],
                    'type' => 'hierarchy',
                    'has_location' => $this->hasLocation
                ]
            ];
        }
        return $formsExtra;
    }

    private function processInput(array $input, array $multipleChoiceQuestionTypes, $parentRef = null, $branchRef = null): void
    {
        $this->inputsExtra[$input['ref']] = [
            'data' => $this->flattenInput($input)
        ];

        // Handle location
        if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
            $this->hasLocation = true;

            // Determine if we are inside a hierarchy
            // Priority 1: If we have both branch AND group (e.g. location in group in branch)
            // The tests expect input_ref = location ref and branch_ref = group ref
            if ($branchRef && $parentRef && $parentRef !== $branchRef) {
                $inputRef = $input['ref'];
                $locationBranchRef = $parentRef;
            }
            // Priority 2: Simple branch (location in branch)
            // Tests expect: input_ref = branch ref, branch_ref = location ref
            elseif ($branchRef) {
                $inputRef = $branchRef;
                $locationBranchRef = $input['ref'];
            }
            // Priority 3: Simple group (location in group)
            // Tests expect: input_ref = location ref, branch_ref = group ref
            elseif ($parentRef) {
                $inputRef = $input['ref'];
                $locationBranchRef = $parentRef;
            }
            // Priority 4: Top level
            else {
                $inputRef = $input['ref'];
                $locationBranchRef = null;
            }

            $this->locationInputs[] = [
                'question' => $input['question'],
                'input_ref' => $inputRef,
                'branch_ref' => $locationBranchRef
            ];
        }

        // Handle Multiple Choice
        if (in_array($input['type'], $multipleChoiceQuestionTypes)) {
            if ($branchRef) {
                // If it's inside a branch, it goes into multipleChoiceBranchInputs
                if (!isset($this->multipleChoiceBranchInputs[$branchRef])) {
                    $this->multipleChoiceBranchInputs[$branchRef] = ['order' => []];
                }
                $this->multipleChoiceBranchInputs[$branchRef]['order'][] = $input['ref'];
                $this->multipleChoiceBranchInputs[$branchRef][$input['ref']] = [
                    'question' => $input['question'],
                    'possible_answers' => $this->convertToHashMap($input['possible_answers'])
                ];
            } else {
                // Top-level or nested in a group (not inside a branch)
                $this->multipleChoiceInputRefsInOrder[] = $input['ref'];
                $this->multipleChoiceInputs[$input['ref']] = [
                    'question' => $input['question'],
                    'possible_answers' => $this->convertToHashMap($input['possible_answers'])
                ];
            }
        }

        // Recurse into groups
        if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
            $groupInputs = $input['group'];
            $currentGroupInputRefs = [];
            foreach ($groupInputs as $groupInput) {
                $currentGroupInputRefs[] = $groupInput['ref'];
                // When recursing into a group, pass the group ref as parent if we are not in a branch
                // If we ARE in a branch, branchRef stays as is for MC bucket logic
                $this->processInput($groupInput, $multipleChoiceQuestionTypes, $input['ref'], $branchRef);
            }
            $this->groups[$input['ref']] = $currentGroupInputRefs;
        }

        // Recurse into branches
        if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
            $branchInputs = $input['branch'];
            $currentBranchInputRefs = [];
            foreach ($branchInputs as $branchInput) {
                $currentBranchInputRefs[] = $branchInput['ref'];
                // When recursing into a branch, branchRef becomes the current branch input ref
                $this->processInput($branchInput, $multipleChoiceQuestionTypes, $input['ref'], $input['ref']);
            }
            $this->branches[$input['ref']] = $currentBranchInputRefs;
        }
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

    private function flattenInput(array $input): array
    {
        $flattened = $input;
        $flattened['group'] = [];
        $flattened['branch'] = [];
        return $flattened;
    }
}
