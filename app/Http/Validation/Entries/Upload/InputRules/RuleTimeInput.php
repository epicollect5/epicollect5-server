<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use DateTime;
use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use Log;
use Throwable;

class RuleTimeInput extends RuleInputBase
{
    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param ProjectDTO $project
     */
    public function setRules($inputDetails, $answer, ProjectDTO $project): void
    {
        // Set rules based on the input details
        // Source will be the input ref

        // Check date is in correct format
        //IMPORTANT: if we use "time" Laravel validation goes belly up!!!!!!!!!!!!!
        $this->rules[$inputDetails['ref']] = ['date'];

        // Set remaining rules in the parent class
        parent::setRules($inputDetails, $answer, $project);
    }

    public function additionalChecks($inputDetails, $answer, ProjectDTO $project, EntryStructureDTO $entryStructure): array|string|null
    {
        //if this question is not required, skip extra checks
        if ($inputDetails['is_required'] === false && $answer === '') {
            return $answer;
        }

        $failedChecks = [];
        $exceptionMessage = null;

        //ISO 8601 format only -> 1977-05-22T00:00:00.000
        $regex = '/([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}.[0-9]{3})+/';
        if (!preg_match_all($regex, $answer)) {
            $failedChecks[] = 'regex';
            $this->errors[$inputDetails['ref']] = ['ec5_79'];
        }

        //valid date?
        if (!strtotime($answer)) {
            $failedChecks[] = 'strtotime';
            $this->errors[$inputDetails['ref']] = ['ec5_79'];
        }

        //Let's check if H:i:s is actually a valid time
        $timePart = '';
        try {
            $timePart = explode('T', $answer ?? '')[1];
            $timePart = explode('.', $timePart ?? '')[0];
        } catch (Throwable $e) {
            if (!in_array('validateTime', $failedChecks, true)) {
                $failedChecks[] = 'validateTime';
            }
            $exceptionMessage = $e->getMessage();
            $this->errors[$inputDetails['ref']] = ['ec5_79'];
        }

        if (!$this->validateTime($timePart)) {
            if (!in_array('validateTime', $failedChecks, true)) {
                $failedChecks[] = 'validateTime';
            }
            $this->errors[$inputDetails['ref']] = ['ec5_79'];
        }

        if (!empty($failedChecks)) {
            $context = [
                'project_slug' => $project->slug,
                'input_ref' => $inputDetails['ref'],
                'date' => $answer,
                'time' => $timePart,
                'failed_checks' => $failedChecks,
            ];
            if ($exceptionMessage !== null) {
                $context['exception'] = $exceptionMessage;
            }
            Log::warning('Time wrong format uploaded', $context);
        }

        return $answer;
    }

    //see t.ly/YEox
    private function validateTime($time, $format = 'H:i:s'): bool
    {
        $d = DateTime::createFromFormat($format, $time);
        return $d && $d->format($format) == $time;
    }
}
