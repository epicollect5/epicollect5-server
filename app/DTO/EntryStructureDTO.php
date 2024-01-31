<?php

namespace ec5\DTO;

use Hash;
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
    protected $branchOwnerEntryDbId;
    protected $possibleAnswers = [];
    protected $existingEntry = null;
    protected $isBranch = false;
    protected $file = null;

    public function createStructure($data)
    {
        $this->data = $data;
    }

    /**
     * Store the db id for a branch owner entry
     *
     * @param $owner
     */
    public function addBranchOwnerEntryToStructure($owner)
    {
        $this->branchOwnerEntryDbId = $owner->id;
    }

    /**
     * @return mixed
     */
    public function getBranchOwnerEntryDbId()
    {
        return $this->branchOwnerEntryDbId;
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
        return $this->getEntry()['created_at'] ?? date('Y-m-d H:i:s');
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
     * Get Entry array
     *
     */
    public function getEntry(): array
    {
        return (count($this->data[$this->getEntryType()]) > 0 ? $this->data[$this->getEntryType()] : []);
    }

    /**
     * Get Entry array
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
     * @param $inputRef
     * @param $geoJson
     */
    public function addGeoJson($inputRef, $geoJson)
    {
        $this->geoJson[$inputRef] = $geoJson;
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
    public function getParentFormRef()
    {
        return $this->data['relationships']['parent']['data']['parent_form_ref'] ?? '';
    }

    /**
     * @param $possibleAnswer
     */
    public function addPossibleAnswer($possibleAnswer)
    {
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

    /**
     * Set this entry structure to be a branch
     */
    public function setAsBranch()
    {
        $this->isBranch = true;
    }

    public function isBranch(): bool
    {
        return $this->isBranch;
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
    public function getFile()
    {
        return $this->file ?? null;
    }

    /**
     * Check if this entry can be an edit for the supplied $dbEntry
     *
     * @param $dbEntry
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
        // Is the device id (non-empty) the same (for non web platforms)?
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

    public function shouldUpdateUserId(): bool
    {
        return $this->updateUserId;
    }
}
