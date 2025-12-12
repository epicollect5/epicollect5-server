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
use Throwable;

class CreateEntryService
{
    use Entries;

    public array $errors = [];

    /**
     * @throws Throwable
     */
    public function create(ProjectDTO $project, EntryStructureDTO $entryStructure, $isBulkUpload = false): bool
    {
        $entry = [];
        $mediaTypes = array_keys(config('epicollect.strings.media_input_types'));
        $entryData = [];

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
                    //Add the missing "T" so the date format matches Javascript format
                    //to mimic an entry uploaded. MySQL format does not have the "T" but a space " ")
                    if ($entryStructure->isBranch()) {
                        $entryData['branch_entry']['created_at'] = $createdAt->format('Y-m-d\TH:i:s.000\Z'); //override uploaded entry
                    } else {
                        $entryData['entry']['created_at'] = $createdAt->format('Y-m-d\TH:i:s.000\Z'); //override uploaded entry
                    }

                    $entry['entry_data'] = json_encode($entryData); //encode entry

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
                } else {
                    // Add additional keys/values for hierarchy entries
                    $entry['parent_uuid'] = $entryStructure->getParentUuid();
                    $entry['parent_form_ref'] = $entryStructure->getParentFormRef();
                }
                $entryRowId = $this->storeEntry($entryStructure, $entry);
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
        } catch (Throwable $e) {
            Log::error(json_last_error_msg(), ['$entryData' => $entryData]);
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

    /**
     * @throws Throwable
     */
    protected function updateExistingEntry(EntryStructureDTO $entryStructure, $entry, $editEntry): int
    {
        $table = config('epicollect.tables.entries');
        $tableJson = config('epicollect.tables.entries_json');
        if ($entryStructure->isBranch()) {
            $table = config('epicollect.tables.branch_entries');
            $tableJson = config('epicollect.tables.branch_entries_json');
        }

        $entryInsertId = $editEntry->id;
        $projectId = $entryStructure->getProjectId(); // Need project_id for JSON table


        //Do we need to update the user_id?
        $newUserId = null;
        if ($entryStructure->shouldUpdateUserId()) {
            $newUserId = $entryStructure->getUserId();
            // Update the array for completeness, though we will use $newUserId directly
            $entry['user_id'] = $newUserId;
        }

        // Perform all updates within a transaction for safety
        try {
            DB::beginTransaction();
            // 1. Insert/Update into the dedicated JSON table (UPSERT logic)
            // This ensures the row exists in the JSON table.
            // It UPDATES existing rows or INSERTS a new one for old entries being migrated.
            DB::table($tableJson)->updateOrInsert(
                [
                    'entry_id' => $editEntry->id,
                    'project_id' => $projectId,
                ],
                [
                    'entry_data' => $entry['entry_data'],
                    'geo_json_data' => $entry['geo_json_data'] ?? null,
                ]
            );

            // 2. Update main table (non-JSON fields)
            // Note: entry_data and geo_json_data are set to null to avoid conflicts
            DB::table($table)
                ->where('id', $editEntry->id)
                ->update([
                    'title' => $entry['title'],
                    'uploaded_at' => $entry['uploaded_at'],
                    'entry_data' => null,
                    'geo_json_data' => null,
                    //update user_id if needed
                    'user_id' => $newUserId ?? $editEntry->user_id,
                ]);
            DB::commit();
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return 0;
        }

        return $entryInsertId;
    }
}
