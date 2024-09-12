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

        //ISO 8601 format only -> 1977-05-22T00:00:00.000
        $regex = '/([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}.[0-9]{3})+/';
        if (!preg_match_all($regex, $answer)) {
            Log::error('Date wrong format uploaded - regex failed', [
                'project slug' => $project->slug,
                'date' => $answer
            ]);
            $this->errors[$inputDetails['ref']] = ['ec5_79'];
        }

        //valid date?
        if (!strtotime($answer)) {
            Log::error('Date wrong format uploaded - strtotime failed', [
                'project slug' => $project->slug,
                'date' => $answer
            ]);
            $this->errors[$inputDetails['ref']] = ['ec5_79'];
        }

        //Let's check if Y-m-d is actually a valid date in the history of time
        $timePart = '';
        try {
            $timePart = explode('T', $answer ?? '')[1];
            $timePart = explode('.', $timePart ?? '')[0];
        } catch (Throwable $e) {
            Log::error('Time wrong format uploaded - validateTime failed', [
                'project slug' => $project->slug,
                'time' => $timePart,
                'exception' => $e->getMessage()
            ]);
            $this->errors[$inputDetails['ref']] = ['ec5_79'];
        }

        if (!$this->validateTime($timePart)) {
            Log::error('Time wrong format uploaded - validateTime failed', [
                'project slug' => $project->slug,
                'time' => $timePart
            ]);
            $this->errors[$inputDetails['ref']] = ['ec5_79'];
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
