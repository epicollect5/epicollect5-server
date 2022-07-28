<?php

namespace ec5\Repositories\QueryBuilder\Entry\Upload\Create;

use ec5\Libraries\EC5Logger\EC5Logger;
use ec5\Models\Projects\Project;
use ec5\Models\Entries\EntryStructure;

use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository;
use ec5\Repositories\QueryBuilder\Base;

use Exception;
use Config;

abstract class CreateRepository extends Base
{
    protected $table;
    protected $statsRepository;
    protected $isBranchEntry;
    protected $isBulkUpload = false;


    /**
     * CreateRepository constructor.
     * @param $table
     * @param StatsRepository $statsRepository
     * @param $isBranchEntry
     */
    public function __construct($table, StatsRepository $statsRepository, $isBranchEntry)
    {
        $this->table = $table;
        $this->isBranchEntry = $isBranchEntry;
        $this->statsRepository = $statsRepository;

        parent::__construct();
    }

    /**
     * Create the entry and all associated answers
     *
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    public function create(Project $project, EntryStructure $entryStructure, $isBulkUpload = false)
    {
        // Start the transaction
        $this->startTransaction();

        $done = $this->tryEntryCreate($project, $entryStructure, $isBulkUpload);
        // Add entry content and answers to database
        if (!$done) {
            EC5Logger::error('Insert unsuccessful', $project, $this->errors());
            // Rollback if any errors
            $this->doRollBack();
        }

        return $done;
    }

    /**
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @param bool $isBulkUpload
     * @return bool
     */
    private function tryEntryCreate(Project $project, EntryStructure $entryStructure, $isBulkUpload = false)
    {
        $done = false;
        $entry = [];
        $mediaTypes = Config::get('ec5Enums.media_input_types');

        try {

            // Get the validated entry data
            $entryData = $entryStructure->getValidatedEntry();

            // Do we have an edit here
            $existingEntry = $entryStructure->getExisting();

            // Build entry
            EC5Logger::info('Building entries table array', $project);
            // EDITABLE FIELDS - fields that we want to update if we have an edit and add if we have a new entry
            // These will override the original values of an existing entry
            $entry['entry_data'] = json_encode($entryData);
            // Add geo location field if available
            if ($entryStructure->hasGeoLocation()) {
                $entryStructure->addPossibleAnswersToGeoJson();
                $entry['geo_json_data'] = json_encode($entryStructure->getGeoJson());
            }
            $entry['title'] = $entryStructure->getTitle();

            $entry['uploaded_at'] = date('Y-m-d H:i:s');

            // If we have an edit, update only the allowed above fields
            if ($existingEntry) {

                //mobile, web or bulk upload?

                if ($isBulkUpload) {
                    /**
                     * This is a bulk edit so do not touch "created_at" in the DB, but get its existing
                     * value and override the entry just uploaded
                     * We do this to fix bulk upload edits
                     * where the created_at value is always re-generated
                     * on the front end with the current date at the time of upload
                     */
                    $createdAt = $existingEntry->created_at; //existing value
                    //add the missing "T" so the date format matches Javascript format (like an entry uploaded. MySQL format does not have the "T" but a space " ")
                    $createdAt = str_replace(' ', 'T', $createdAt);
                    $entryData['entry']['created_at'] = $createdAt; //override uploaded entry
                    $entry['entry_data'] = json_encode($entryData); //encode entry
                    //todo does the above not fail for branches as should be 'branch-entry' type?

                    /**
                     * Skip media files by getting existing answers (files) and
                     * override entry just uploaded
                     *
                     * We do this to fix bulk uploads with media questions where
                     * the answers will be "" to bypass validation
                     *
                     * Media file cannot be uploaded in bulk
                     */

                    //get existing entry data
                    $existingEntryData = json_decode($existingEntry->entry_data, true);
                    //entry or branch entry?
                    $entryType = $existingEntryData['type'];
                    //get answers
                    $existingAnswers = $existingEntryData[$entryType]['answers'];
                    //get inputs from projectExtra
                    $inputs = $project->getProjectExtra()->getInputs();

                    foreach ($existingAnswers as $inputRef => $answer) {
                        $inputType = $inputs[$inputRef]['data']['type'];
                        //is the input a media question?
                        if (in_array($inputType, $mediaTypes)) {
                            //get existing value to override entry just uploaded
                            //media questions are left untouched this way
                            $entryData[$entryType]['answers'][$inputRef] = $answer;
                        }
                    }
                    //encode entry
                    $entry['entry_data'] = json_encode($entryData);
                }

                $entryInsertId = $this->updateExistingEntry($entryStructure, $entry, $existingEntry);
            } else {
                // If we have a new entry, create all fields necessary for db insertion
                $entryInsertId = $this->insertNewEntry($entryStructure, $entry);
            }

            if (!$entryInsertId) {
                $this->errors['entry_create'] = ['ec5_95'];
                return $done;
            }

            // Update the stats for the entry
            if (!$this->updateStats($project, $entryStructure)) {
                $this->errors['entry_create'] = ['ec5_94'];
                return $done;
            }

            // All good
            $this->doCommit();
            $done = true;
        } catch (Exception $e) {
            EC5Logger::error('Exception thrown', $project, [json_encode($e)]);
            $this->errors[$e->getMessage()] = ['ec5_45'];
        }

        return $done;
    }

    /**
     * @param EntryStructure $entryStructure
     * @param $entry
     * @return int
     */
    protected function insertNewEntry(EntryStructure $entryStructure, $entry)
    {
        // Set the entry params to be added
        $entry['uuid'] = $entryStructure->getEntryUuid();
        $entry['form_ref'] = $entryStructure->getFormRef();
        $entry['created_at'] = $entryStructure->getDateCreated();
        $entry['project_id'] = $entryStructure->getProjectId();
        $entry['device_id'] = $entryStructure->getHashedDeviceId();
        $entry['platform'] = $entryStructure->getPlatform();
        $entry['user_id'] = $entryStructure->getUserId();

        // Attempt to insert db entry
        return $this->insertReturnId($this->table, $entry);
    }

    /**
     * @param EntryStructure $entryStructure
     * @param $entry
     * @param $editEntry
     * @return int
     */
    protected function updateExistingEntry(EntryStructure $entryStructure, $entry, $editEntry)
    {
        $entryInsertId = $editEntry->id;

        // Should the user id of the entry be updated?
        if ($entryStructure->shouldUpdateUserId()) {
            $entry['user_id'] = $entryStructure->getUserId();
        }

        // Update db entry. Note: laravel will only update if the row exists and a change has been made
        $this->updateById($this->table, $entryInsertId, $entry);

        return $entryInsertId;
    }

    /**
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    protected function updateStats(Project $project, EntryStructure $entryStructure)
    {
        // Skip update project stats counts (project_stats table)
        //imp: this was expensive to be done per each entry!
        // if (!$this->statsRepository->updateProjectEntryStats($project)) {
        //     $this->errors['entry_create'] = ['ec5_94'];
        //     EC5Logger::error('Failed to update stats', $project, $this->errors);
        //     return false;
        // }

        // Update additional stats in entries table (child_counts, branch_counts)
        //imp: can still be done per each entry since most of the projects do not have child forms or branches
        //imp: also branches are stored in a separate table, counts will be faster
        if (!$this->statsRepository->updateAdditionalStats($project, $entryStructure)) {
            $this->errors['entry_create'] = ['ec5_94'];
            EC5Logger::error('Failed to update stats additional', $project, $this->errors);
            return false;
        }
        return true;
    }
}
