<?php

namespace ec5\Services\Mapping;


class ProjectMappingService
{
    private $inputCounter;

    public function createEC5AUTOMapping(array $projectExtra): array
    {
        // Set the auto-generated map2
        return [
            'forms' => $this->getMappedForms($projectExtra),
            'name' => config('epicollect.mappings.default_mapping_name'),
            'is_default' => true,
            'map_index' => 0
        ];
    }

    public function getMappedForms($projectExtra): array
    {
        // Reset the input counter
        $this->inputCounter = 0;
        //get forms
        $forms = $projectExtra['forms'];
        // Initialise an empty map
        $map = [];
        // Loop forms and get the EC5 AUTO map for each
        foreach ($forms as $formRef => $form) {
            // Map all the form's inputs
            $map[$formRef] = $this->getMappedInputs($projectExtra, $formRef, $form['inputs']);
        }
        return $map;
    }

    /**
     * Map a set of inputs
     *
     * @param $formRef
     * @param array $inputRefs
     * @return array
     */
    private function getMappedInputs($projectExtra, $formRef, array $inputRefs): array
    {
        $mappedInputs = [];
        $excludedTypes = array_keys(config('epicollect.strings.exclude_from_mapping'));

        foreach ($inputRefs as $inputRef) {
            $inputData = $projectExtra['inputs'][$inputRef]['data'];
            // Check the input type is not in the $excludedTypes array
            if (!in_array($inputData['type'], $excludedTypes)) {
                // Map the top level input
                $mappedInputs = array_merge($mappedInputs, $this->getMappedInput($projectExtra, $formRef, $inputData));
            }
        }
        return $mappedInputs;
    }

    private function getMappedInput($projectExtra, $formRef, $inputData): array
    {
        $inputRef = $inputData['ref'];
        $mappedInput = [];

        $this->inputCounter++;
        $question = $inputData['question'];

        // Map current input
        $mappedInput[$inputRef] = [
            'map_to' => $this->generateMapTo($this->inputCounter, $question),
            'hide' => false
        ];

        // Default empty array for possible_answers, group and branch
        $mappedInput[$inputRef]['possible_answers'] = [];
        $mappedInput[$inputRef]['group'] = [];
        $mappedInput[$inputRef]['branch'] = [];

        // Further map processing for certain input types
        $type = $inputData['type'];
        switch ($type) {
            case 'group':
                // Map the group
                $groupInputs = $projectExtra['forms'][$formRef]['group'][$inputRef];
                if (count($groupInputs) > 0) {
                    $mappedInput[$inputRef]['group'] = $this->getMappedInputs($projectExtra, $formRef, $groupInputs);
                }
                break;
            case 'branch':
                // Map the branch
                $branchInputs = $projectExtra['forms'][$formRef]['branch'][$inputRef];
                if (count($branchInputs) > 0) {
                    $mappedInput[$inputRef]['branch'] = $this->getMappedInputs($projectExtra, $formRef, $branchInputs);
                }
                break;

            //map possible answers for those input types which have them
            case 'dropdown':
            case 'radio':
            case 'checkbox':
            case 'searchsingle':
            case 'searchmultiple':
                // Map the possible answers
                $mappedInput[$inputRef]['possible_answers'] = $this->mapPossibleAnswers($inputData);
                break;

        }

        return $mappedInput;
    }

    private function mapPossibleAnswers($inputData): array
    {
        $mappedPossibleAnswers = [];

        foreach ($inputData['possible_answers'] as $possibleAnswer) {
            $answerRef = $possibleAnswer['answer_ref'];
            $text = $possibleAnswer['answer'];
            $mappedPossibleAnswers[$answerRef] = ['map_to' => $text];
        }

        return $mappedPossibleAnswers;
    }

    /**
     * @param $index
     * @param $question
     * @return string
     */
    private function generateMapTo($index, $question): string
    {
        //remove spaces
        $question = trim($question);
        //prepend index to make each map ref unique project wide
        $question = $index . '_' . $question;
        // Replace sequences of spaces with underscore
        $question = preg_replace('/  */', '_', $question);
        // Remove any unaccepted chars
        $question = preg_replace('/[^A-Za-z0-9_]/', '', $question);
        // Substring if too long
        return substr(
            preg_replace('/\\s+/', '_', $question),
            0,
            config('epicollect.limits.project_mappings.map_key_length')
        );
    }

}