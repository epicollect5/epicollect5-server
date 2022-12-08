<?php

namespace ec5\Models\Entries;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use ec5\Models\ProjectRoles\ProjectRole;
use Config;
use Hash;
use App;

class EntryStructure
{
    /**
     * @var
     */
    protected $data = [];

    /**
     * @var
     */
    protected $answers = [];

    /**
     * @var
     */
    protected $userId = 0;

    /**
     * @var
     */
    protected $updateUserId = false;

    /**
     * @var
     */
    protected $projectId = 0;

    /**
     * @var ProjectRole|null
     */
    protected $projectRole = null;

    /**
     * @var
     */
    protected $geoJson = [];

    /**
     * @var
     */
    protected $branchOwnerEntryDbId;

    /**
     * @var
     */
    protected $possibleAnswers = [];

    /**
     * @var null
     */
    protected $existingEntry = null;

    /**
     * @var bool
     */
    protected $isBranch = false;

    /**
     * @var null
     */
    protected $file = null;

    /**
     * @param $data
     */
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
     * @param ProjectRole $projectRole
     */
    public function setProjectRole(ProjectRole $projectRole)
    {
        $this->projectRole = $projectRole;
    }

    /**
     */
    public function getProjectRole()
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

    /**
     * @return string|null
     */
    public function getEntryType()
    {
        return $this->data['type'] ?? '';
    }

    /**
     * @return string|null
     */
    public function getDateCreated()
    {
        /**
         * Sometimes this is failing, so retunr the uploaded_at which
         * is the current date, otherwise it will be saved as 0000... in the db
         * since we are returning an empty string
         * */
        return $this->getEntry()['created_at'] ?? date('Y-m-d H:i:s');
    }

    /**
     * @return string|null
     */
    public function getDeviceId()
    {
        return $this->getEntry()['device_id'] ?? '';
    }

    /**
     * @return string|null
     */
    public function getHashedDeviceId()
    {
        return $this->getEntry()['device_id'] ? Hash::make($this->getEntry()['device_id']) : '';
    }

    /**
     * @return string|null
     */
    public function getPlatform()
    {
        return $this->getEntry()['platform'] ?? '';
    }

    /**
     * @return string|null
     */
    public function getFormRef()
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
     * @return array
     */
    public function getEntry()
    {
        return (count($this->data[$this->getEntryType()]) > 0 ? $this->data[$this->getEntryType()] : []);
    }

    /**
     * Get Entry array
     *
     * @return array
     */
    public function getValidatedEntry()
    {
        // Reconstruct an entire entry, validated
        $entry = [];

        // Filter out the required values from the uploaded entry, for the data
        foreach (array_keys(Config::get('ec5ProjectStructures.entry_data')) as $key) {
            $entry[$key] = $this->data[$key] ?? '';
        }

        // Filter out the required values from the uploaded entry, for the entry
        foreach (array_keys(Config::get('ec5ProjectStructures.entry')) as $key) {
            if (!in_array($key, Config::get('ec5Enums.exclude_from_entry_data'))) {
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

    /**
     * @return array
     */
    public function getValidatedAnswers()
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

    /**
     * @return array
     */
    public function getAnswers(): array
    {
        return $this->getEntry()['answers'] ?? [];
    }

    /**
     * Do we have an entry type that has 'answers'?
     *
     * @return bool
     */
    public function hasAnswers()
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

    /**
     * @return string
     */
    public function getOwnerUuid()
    {
        return $this->data['relationships']['branch']['data']['owner_entry_uuid'] ?? '';
    }

    /**
     * @return string
     */
    public function getOwnerInputRef()
    {
        return $this->data['relationships']['branch']['data']['owner_input_ref'] ?? '';
    }

    /**
     * @return string
     */
    public function getParentUuid()
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

    /**
     * @return bool
     */
    public function hasGeoLocation()
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

    /**
     * @return bool
     */
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

    /**
     * @return bool
     */
    public function isBranch()
    {
        return $this->isBranch;
    }

    /**
     * @return string
     */
    public function getTitle()
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
     * @return bool
     */
    public function canEdit($dbEntry): bool
    {
        // Does the user have a canEditData role?
        // 1 - First we check the user role, as CREATOR, MANAGER, CURATOR
        // can edit all entries regardless of ownhership
        $requestedProjectRole = $this->getProjectRole();
        if ($requestedProjectRole->canEditData()) {
            return true;
        }

        // Is the user the same (non zero)?
        // 2 - For COLLECTOR role, we check if the user ID matches
        // the ID of 0 (zero) is assigned when the entry 
        //is uploaded without authentication (from the mobile apps)
        \Log::error('1 - $dbEntry->user_id ' . $dbEntry->user_id);
        \Log::error('2 - $this->getUserId() ' . $this->getUserId());
        if ($dbEntry->user_id != 0 && $dbEntry->user_id == $this->getUserId()) {
            return true;
        }

        // Is the device id (non empty) the same (for non web platforms)?
        // 3 - on native apps, we can check the device unique identifier
        // if it matches, we perform the edit 
        \Log::error('3 entry platform ' . $this->getPlatform());
        \Log::error('4 entry device id ' . $this->getDeviceId());
        \Log::error('5 db device is' . $dbEntry->device_id);
        if (
            $this->getPlatform() != Config::get('ec5Enums.web_platform') &&
            !empty($this->getDeviceId()) &&
            Hash::check($this->getDeviceId(), $dbEntry->device_id)
        ) {

            // If the user is logged in and the existing user_id is 0, replace the user_id
            if ($this->getUserId() != 0 && $dbEntry->user_id == 0) {
                $this->updateUserId = true;
            }

            return true;
        }

        // 4 - For debugging PWA, allow edits without authentication
        if (App::isLocal()) {
            \Log::error('6 entry platform ' . $this->getPlatform());
            \Log::error('7 user id ' . $this->getUserId());
            if ($this->getPlatform() === Config::get('ec5Enums.web_platform')) {
                if ($this->getUserId() === 0) {
                    return true;
                }
            }
        }

        // Can't edit
        return false;
    }

    /**
     * @return bool
     */
    public function shouldUpdateUserId()
    {
        return $this->updateUserId;
    }
}
