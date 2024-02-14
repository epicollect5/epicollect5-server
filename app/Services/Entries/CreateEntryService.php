<?php

namespace ec5\Services\Entries;


use DB;
use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Models\Counters\BranchEntryCounter;
use ec5\Models\Counters\EntryCounter;
use ec5\Traits\Eloquent\Entries;
use Exception;
use Log;

class CreateEntryService
{
    use Entries;

    public $errors;

    public function create(ProjectDTO $project, EntryStructureDTO $entryStructure, $isBulkUpload = false): bool
    {
        $entry = [];
        $mediaTypes = array_keys(config('epicollect.strings.media_input_types'));

        try {
            DB::beginTransaction();
            // Get the validated entry data
            $entryData = $entryStructure->getValidatedEntry();
            // Do we have an edit here?
            $existingEntry = $entryStructure->getExisting();

            // Build entry object
            // EDITABLE FIELDS - fields that we want to update if we have an edit and add if we have a new entry
            // These will override the original values of an existing entry
            $entry['entry_data'] = json_encode($entryData);
            // Add geolocation field if available
            if ($entryStructure->hasGeoLocation()) {
                //to make the dataviewer pie chart markers
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

                $entryRowId = $this->updateExistingEntry($entryStructure, $entry, $existingEntry);
            } else {
                //Store the new entry
                if ($entryStructure->isBranch()) {
                    // Add additional keys/values for branch entries
                    $entry['owner_entry_id'] = $entryStructure->getOwnerEntryID();
                    $entry['owner_uuid'] = $entryStructure->getOwnerUuid();
                    $entry['owner_input_ref'] = $entryStructure->getOwnerInputRef();
                    $entryRowId = $this->storeEntry($entryStructure, $entry);
                } else {
                    // Add additional keys/values for hierarchy entries
                    $entry['parent_uuid'] = $entryStructure->getParentUuid();
                    $entry['parent_form_ref'] = $entryStructure->getParentFormRef();
                    $entryRowId = $this->storeEntry($entryStructure, $entry);
                }
            }

            if (!$entryRowId) {
                $this->errors['entry-create'] = ['ec5_45'];
                throw new Exception(config('epicollect.codes.ec5_95'));
            }

            // Update the stats for the entry
            if (!$this->updateEntriesCounts($project, $entryStructure)) {
                $this->errors['stats-update'] = ['ec5_45'];
                throw new Exception(config('epicollect.codes.ec5_94'));
            }

            // All good
            DB::commit();
            return true;
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            $this->errors['create-entry-service'] = ['ec5_45'];
            DB::rollBack();
            return false;
        }
    }

    protected function updateEntriesCounts(ProjectDTO $project, EntryStructureDTO $entryStructure): bool
    {
        // Update additional stats in entries table (child_counts, branch_counts)
        //imp: can still be done per each entry since most of the projects do not have child forms or branches
        //imp: also branches are stored in a separate table, counts will be faster
        $entryCounter = $entryStructure->isBranch() ? new BranchEntryCounter() : new EntryCounter();
        if (!$entryCounter->updateCounters($project, $entryStructure)) {
            return false;
        }
        return true;
    }

    protected function updateExistingEntry(EntryStructureDTO $entryStructure, $entry, $editEntry): int
    {
        $table = config('epicollect.tables.entries');
        if ($entryStructure->isBranch()) {
            $table = config('epicollect.tables.branch_entries');
        }

        $entryInsertId = $editEntry->id;

        // Should the user id of the entry be updated?
        if ($entryStructure->shouldUpdateUserId()) {
            $entry['user_id'] = $entryStructure->getUserId();
        }

        // Update db entry. imp: laravel will only update if the row exists and a change has been made
        DB::table($table)
            ->where('id', $editEntry->id)
            ->update($entry);

        return $entryInsertId;
    }
}