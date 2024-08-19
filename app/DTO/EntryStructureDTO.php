<?php

namespace ec5\DTO;

use Carbon\Carbon;
use Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EntryStructureDTO
{
    protected $data = [];
    protected $answers = [];
    protected $userId = 0;
    protected $updateUserId = false;
    protected $projectId = 0;
    /**
     * @var ProjectRoleDTO|null
     */
    protected $projectRole = null;
    protected $geoJson = [];
    protected $branchOwnerEntryRowId;
    protected $possibleAnswers = [];
    protected $existingEntry = null;
    protected $file = null;

    public function init($payload, $projectId, $requestedUser, $requestedProjectRole)
    {
        // Get current user (from $requestedUser since we do not know what guard was used)
        $user = $requestedUser;
        // Initialise entry structure based on request payload
        $this->createStructure($payload);
        // Add user id (0 if null) to entry structure
        $this->setUserId(!empty($user) ? $user->id : 0);
        // Add project id to entry structure
        $this->setProjectId($projectId);
        // Add the project role to entry structure
        $this->setProjectRole($requestedProjectRole);
        // If there is a file in the request, load into the entry structure
        if (request()->hasFile('name')) {
            $this->setFile(request()->file('name'));
        }
    }


    public function createStructure($payload)
    {
        /**
         * @var array $payload
         *   Data array.
         *   - type: string - Entry type.
         *   - id: string - Entry ID.
         *   - attributes: array - Entry attributes.
         *     - form: array - Form attributes.
         *       - ref: string - Reference of the form.
         *       - type: string - Type of the form.
         *   - relationships: array - Entry relationships.
         *     - parent: array - Parent relationship.
         *       - data: array - Parent data.
         *         - parent_form_ref: string - Parent form reference.
         *         - parent_entry_uuid: string - Parent entry UUID.
         *     - branch: array - Branch relationship.
         *   - entry: array - Entry details.
         *     - entry_uuid: string - Entry UUID.
         *     - created_at: string - Creation timestamp.
         *     - device_id: string - Device ID.
         *     - platform: string - Platform.
         *     - title: string - Title.
         *     - answers: array - Answers array.
         *       - {inputRef}: array - Answer details.
         *         - answer: string - Answer text.
         *         - was_jumped: bool - Was jumped.
         *     - project_version: string - Project version.
         */


        $type = $payload['type']; // entry, branch_entry, file_entry, archive
        // Common data structure
        $this->data = [
            'type' => $payload['type'],
            'id' => $payload['id'],
            'attributes' => [
                'form' => [
                    'ref' => $payload['attributes']['form']['ref'],
                    'type' => 'hierarchy'
                ]
            ],
            'relationships' => [
                'parent' => [
                    'data' => [
                        'parent_form_ref' => $payload['relationships']['parent']['data']['parent_form_ref'] ?? '',
                        'parent_entry_uuid' => $payload['relationships']['parent']['data']['parent_entry_uuid'] ?? ''
                    ]
                ],
                'branch' => [
                    'data' => [
                        'owner_input_ref' => $payload['relationships']['branch']['data']['owner_input_ref'] ?? '',
                        'owner_entry_uuid' => $payload['relationships']['branch']['data']['owner_entry_uuid'] ?? '',
                    ]
                ]
            ]
        ];

        // Dynamic-type-specific data
        $this->data[$type] = [
            'entry_uuid' => $payload[$type]['entry_uuid'] ?? '',
            'created_at' => $payload[$type]['created_at'] ?? '', // like '2024-02-12T11:32:32.321Z',
            'device_id' => $payload[$type]['device_id'] ?? '',
            'platform' => $payload[$type]['platform'] ?? '', //WEB, Android, iOS
            'project_version' => $payload[$type]['project_version'] ?? '', // like '2024-02-12 11:32:05'
            //when testing uniqueness, we set the below properties
            'input_ref' => $payload[$type]['input_ref'] ?? '',
            'answer' => $payload[$type]['answer'] ?? ''
        ];

        // Additional fields for specific types
        if ($type === config('epicollect.strings.entry_types.file_entry')) {
            $this->data[$type] += [
                'name' => $payload[$type]['name'],
                'type' => $payload[$type]['type'],
                'input_ref' => $payload[$type]['input_ref']
            ];
        } else {
            $this->data[$type] += [
                'title' => $payload[$type]['title'] ?? '',
                'answers' => $payload[$type]['answers'] ?? []
            ];
        }
    }

    /**
     * Store the DB id for a branch owner entry
     *
     * @param $ownerId
     */
    public function setOwnerEntryID($ownerId)
    {
        $this->branchOwnerEntryRowId = $ownerId;
    }

    public function setOwnerEntryUuid($ownerEntryUuid)
    {
        $this->data['relationships']['branch']['data']['owner_entry_uuid'] = $ownerEntryUuid;
    }

    public function getOwnerEntryID(): int
    {
        return $this->branchOwnerEntryRowId;
    }

    /**
     * @param $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @param $projectId
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * @param ProjectRoleDTO $projectRole
     */
    public function setProjectRole(ProjectRoleDTO $projectRole)
    {
        $this->projectRole = $projectRole;
    }

    public function getProjectRole(): ?ProjectRoleDTO
    {
        return $this->projectRole;
    }

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @return mixed
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    public function getEntryType(): ?string
    {
        return $this->data['type'] ?? '';
    }

    public function getDateCreated(): ?string
    {
        /**
         * Sometimes this is failing, so return the uploaded_at which
         * is the current date, otherwise it will be saved as 0000... in the db
         * since we are returning an empty string
         * */
        if (isset($this->getEntry()['created_at'])) {
            //hack due to Laravel 7 adding unwanted .000 (milliseconds)
            // return Carbon::parse($this->getEntry()['created_at'])->format('Y-m-d H:i:s');
            return $this->getEntry()['created_at'];
        } else {
            return date('Y-m-d H:i:s');
        }
    }

    public function getDeviceId(): ?string
    {
        return $this->getEntry()['device_id'] ?? '';
    }

    public function getHashedDeviceId(): ?string
    {
        return $this->getEntry()['device_id'] ? Hash::make($this->getEntry()['device_id']) : '';
    }

    public function getPlatform(): ?string
    {
        return $this->getEntry()['platform'] ?? '';
    }

    public function getFormRef(): ?string
    {
        return $this->data['attributes']['form']['ref'] ?? '';
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return (count($this->data) > 0 ? $this->data : []);
    }

    /**
     * Get the Entry array
     *
     */
    public function getEntry(): array
    {
        return (count($this->data[$this->getEntryType()]) > 0 ? $this->data[$this->getEntryType()] : []);
    }

    /**
     * Get the Entry array
     */
    public function getValidatedEntry(): array
    {
        // Reconstruct an entire entry, validated
        $entry = [];

        // Filter out the required values from the uploaded entry, for the data
        foreach (array_keys(config('epicollect.structures.entry_data')) as $key) {
            $entry[$key] = $this->data[$key] ?? '';
        }

        // Filter out the required values from the uploaded entry, for the entry
        foreach (array_keys(config('epicollect.structures.entry')) as $key) {
            if (!in_array($key, array_keys(config('epicollect.strings.exclude_from_entry_data')))) {
                $entry[$this->getEntryType()][$key] = $this->getEntry()[$key] ?? '';
            }
        }

        // Set the answers
        $entry[$this->getEntryType()]['answers'] = $this->getValidatedAnswers();

        // Get a valid title
        $entry[$this->getEntryType()]['title'] = $this->getTitle();

        // Return the validated entry
        return $entry;
    }

    /**
     * @param $inputRef
     * @param $answer
     */
    public function addValidatedAnswer($inputRef, $answer)
    {
        $this->answers[$inputRef] = $answer;
    }

    public function getValidatedAnswers(): array
    {
        return (count($this->answers) > 0 ? $this->answers : []);
    }

    /**
     * @param $inputRef
     * @return mixed
     */
    public function getValidatedAnswer($inputRef)
    {
        return $this->answers[$inputRef];
    }

    public function getAnswers(): array
    {
        return $this->getEntry()['answers'] ?? [];
    }

    /**
     * Do we have an entry type that has 'answers'?
     */
    public function hasAnswers(): bool
    {
        return in_array($this->getEntryType(), ['entry', 'branch_entry']);
    }

    /**
     * @return int
     */
    public function getEntryUuid()
    {
        return $this->data['id'] ?? '';
    }

    /**
     * @return mixed
     */
    public function getGeoJson()
    {
        return $this->geoJson;
    }

    public function getOwnerUuid(): string
    {
        return $this->data['relationships']['branch']['data']['owner_entry_uuid'] ?? '';
    }

    public function getOwnerInputRef(): string
    {
        return $this->data['relationships']['branch']['data']['owner_input_ref'] ?? '';
    }

    public function getParentUuid(): string
    {
        return $this->data['relationships']['parent']['data']['parent_entry_uuid'] ?? '';
    }

    /**
     * @return string
     */
    public function getParentFormRef(): string
    {
        return $this->data['relationships']['parent']['data']['parent_form_ref'] ?? '';
    }

    /**
     * @param $possibleAnswer
     */
    public function addPossibleAnswer($possibleAnswer)
    {
        if (!is_string($possibleAnswer)) {
            throw new \InvalidArgumentException('The possible answer_ref must be a string.');
        }
        $this->possibleAnswers[$possibleAnswer] = 1;
    }

    /**
     * @return mixed
     */
    public function getPossibleAnswers()
    {
        return $this->possibleAnswers;
    }

    /**
     * Add the possible answers array to the geo json object
     *
     * They are needed for the dataviewer pie charts
     */
    public function addPossibleAnswersToGeoJson()
    {
        foreach ($this->geoJson as $inputRef => $geo) {
            $this->geoJson[$inputRef]['properties']['possible_answers'] = $this->getPossibleAnswers();
        }
    }

    public function hasGeoLocation(): bool
    {
        return count($this->geoJson) > 0;
    }

    public function setHasGeoLocation(): bool
    {
        return count($this->geoJson) > 0;
    }

    /**
     * @param $entry
     */
    public function addExistingEntry($entry)
    {
        $this->existingEntry = $entry;
    }

    /**
     * @return mixed
     */
    public function getExisting()
    {
        return $this->existingEntry;
    }

    public function isEdit(): bool
    {
        return !empty($this->existingEntry);
    }


    public function isBranch(): bool
    {
        return $this->data['type'] === config('epicollect.strings.entry_types.branch_entry');
    }

    public function getTitle(): string
    {
        // Return the title or the entry uuid by default
        return isset($this->getEntry()['title']) && !empty($this->getEntry()['title']) ? $this->getEntry()['title'] : $this->getEntryUuid();
    }

    /**
     * @return mixed|string
     */
    public function getProjectVersion()
    {
        return $this->getEntry()['project_version'] ?? '';
    }

    /**
     * Load an uploaded file into the entry structure
     *
     * @param UploadedFile $file
     */
    public function setFile(UploadedFile $file)
    {
        $this->file = $file;
    }

    /**
     * @return UploadedFile|null
     */
    public function getFile(): ?UploadedFile
    {
        return $this->file ?? null;
    }

    /**
     * Check if this entry can be an edit for the supplied $dbEntry
     *
     * @param $dbEntry
     * @return bool
     */
    public function canEdit($dbEntry): bool
    {
        // Does the user have a canEditData role?
        $requestedProjectRole = $this->getProjectRole();
        if ($requestedProjectRole->canEditData()) {
            return true;
        }
        // Is the user the same (non-zero)?
        if ($dbEntry->user_id != 0 && $dbEntry->user_id == $this->getUserId()) {
            return true;
        }
        // Is the device id (non-empty) the same (for non-web platforms)?
        if ($this->getPlatform() != config('epicollect.mappings.web_platform') &&
            !empty($this->getDeviceId()) &&
            Hash::check($this->getDeviceId(), $dbEntry->device_id)) {

            // If the user is logged in and the existing user_id is 0, replace the user_id
            if ($this->getUserId() != 0 && $dbEntry->user_id == 0) {
                $this->updateUserId = true;
            }
            return true;
        }
        // Can't edit
        return false;
    }

    /**
     * We replaced the user id when
     *  - the upload is from a device
     *  - the existing entry has an ID of zero (0) (public upload without logging in)
     *  - the device ID matches
     *
     *  This means the upload is an edit from the same device,
     *  and the user logged in, so we safely assign the entry to that user
     */
    public function shouldUpdateUserId(): bool
    {
        return $this->updateUserId;
    }

    /**
     * Add the geo json object to the entry structure
     * @param $inputDetails
     *   {
     *       'Ref': '---',
     *       'question': '---',
     *       'type': '---',
     *       '...': '---'
     *   }
     * @param $locationAnswer
     *  {
     *      'Latitude': '---',
     *      'longitude': '---',
     *      'accuracy': '---'
     *  }
     */
    public function addGeoJsonObject($inputDetails, $locationAnswer)
    {
        $geoJson = [];
        $geoJson['type'] = 'Feature';
        $geoJson['id'] = $this->getEntryUuid();
        $geoJson['geometry'] = [
            'type' => 'Point',
            'coordinates' => [
                $locationAnswer['longitude'],
                $locationAnswer['latitude']
            ]
        ];
        $geoJson['properties'] = [
            'uuid' => $this->getEntryUuid(),
            'title' => $this->getTitle(),
            'accuracy' => $locationAnswer['accuracy'],
            'created_at' => date('Y-m-d', strtotime($this->getDateCreated())),

            // Possible answers will be added at the end
            'possible_answers' => [],
        ];

        $this->geoJson[$inputDetails['ref']] = $geoJson;
    }

    public function addAnswerToEntry($input, $answerData)
    {
        // Filter out types which don't need an answer
        if (!in_array($input['type'], array_keys(config('epicollect.strings.inputs_without_answers')))) {
            // Add validated answer to the entry structure
            $this->addValidatedAnswer(
                $input['ref'],
                [
                    'answer' => $answerData['answer'],
                    'was_jumped' => $answerData['was_jumped']
                ]
            );
        }
    }
}
