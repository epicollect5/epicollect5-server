<?php

namespace ec5\Traits\Eloquent;

use ec5\Libraries\Utilities\Common;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\BranchEntryArchive;
use ec5\Models\Entries\Entry;
use ec5\Models\Entries\EntryArchive;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Exception;
use Illuminate\Support\Facades\DB;
use Log;

trait Archiver
{
    /* Archive a project by setting its row as archived.
    All the other tables with the project_id constraint are left untouched

    A scheduled task can remove archived projects one at a time
    if the number of entries is low, for high number of entries we can
    delete them in batches

    Needs to be tested on a production server
    */
    public function archiveProject($projectId, $projectSlug): bool
    {
        try {
            DB::beginTransaction();
            $project = Project::where('id', $projectId)
                ->where('slug', $projectSlug)
                ->first();

            $project->status = config('epicollect.strings.project_status.archived');
            //set name and slug to a unique string (we use a project ref) to avoid duplicates with new projects
            $ref = Generators::projectRef();
            $project->name = $ref;
            $project->slug = $ref;
            //reset all other columns to generic values
            $project->small_description = 'Project was archived';
            $project->description = '';
            $project->logo_url = '';
            $project->access = config('epicollect.strings.project_access.private');
            $project->visibility = config('epicollect.strings.project_visibility.hidden');
            $project->category = config('epicollect.mappings.categories_icons.general');
            $project->can_bulk_upload = config('epicollect.strings.can_bulk_upload.nobody');


            if ($project->save()) {
                DB::commit();
                return true;
            } else {
                DB::rollBack();
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error archiveProject()', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    public function archiveEntries($projectId): bool
    {
        $initialMemoryUsage = memory_get_usage();
        $peakMemoryUsage = memory_get_peak_usage();

        try {

//            //move entries
//            Entry::where('project_id', $projectId)->chunk(100, function ($rowsToMove) {
//                foreach ($rowsToMove as $row) {
//                    $rowToArchive = $row->replicate();
//                    $rowToArchive->created_at = $row->created_at;
//
//                    // make into an array for mass assign.
//                    $rowToArchive = $rowToArchive->toArray();
//
//                    // Perform a manual search for an existing record
//                    $existingRecord = EntryArchive::where('uuid', $rowToArchive['uuid'])
//                        //   ->where('parent_uuid', $rowToArchive['parent_uuid'])
//                        ->where('project_id', $rowToArchive['project_id'])
//                        //    ->where('parent_form_ref', $rowToArchive['parent_form_ref'])
//                        //      ->where('form_ref', $rowToArchive['form_ref'])
//                        //    ->where('title', $rowToArchive['title'])
//                        //    ->where('entry_data', $rowToArchive['entry_data'])
//                        //   ->where('created_at', $rowToArchive['created_at'])
//                        //    ->where('uploaded_at', $rowToArchive['uploaded_at'])
//                        ->first();
//
//                    if (!$existingRecord) {
//                        //create copy to projects_archive table
//                        EntryArchive::create($rowToArchive);
//                    }
//                }
//            });
//            //move branch entries as well
//            BranchEntry::where('project_id', $projectId)->chunk(100, function ($rowsToMove) {
//                foreach ($rowsToMove as $row) {
//                    $rowToArchive = $row->replicate();
//                    $rowToArchive->created_at = $row->created_at;
//                    // make into array for mass assign.
//                    $rowToArchive = $rowToArchive->toArray();
//
//                    // Perform a manual search for an existing record
//                    $existingRecord = BranchEntryArchive::where('uuid', $rowToArchive['uuid'])
////                        ->where('parent_uuid', $rowToArchive['parent_uuid'])
//                        ->where('project_id', $rowToArchive['project_id'])
////                        ->where('parent_form_ref', $rowToArchive['parent_form_ref'])
////                        ->where('form_ref', $rowToArchive['form_ref'])
////                        ->where('title', $rowToArchive['title'])
////                        ->where('entry_data', $rowToArchive['entry_data'])
////                        ->where('created_at', $rowToArchive['created_at'])
////                        ->where('uploaded_at', $rowToArchive['uploaded_at'])
//                        ->first();
//
//                    //create copy to projects_archive table
//                    if (!$existingRecord) {
//                        BranchEntryArchive::create($rowToArchive);
//                    }
//                }
//            });

            do {
                DB::beginTransaction();
                $deleted = Entry::where('project_id', $projectId)->limit(1000)->delete();
                sleep(1);
                Log::info("Deleted " . $deleted . " Entries");
                $peakMemoryUsage = max($peakMemoryUsage, memory_get_peak_usage());
                DB::commit();
            } while ($deleted > 0);

            do {
                DB::beginTransaction();
                $deleted = BranchEntry::where('project_id', $projectId)->limit(1000)->delete();
                sleep(1);
                Log::info("Deleted " . $deleted . " branch Entries");
                $peakMemoryUsage = max($peakMemoryUsage, memory_get_peak_usage());
                DB::commit();
            } while ($deleted > 0);


//            // All rows have been successfully moved, so you can proceed with deleting the original rows
//            foreach (Entry::where('project_id', $projectId)->cursor() as $row) {
//                // Delete the row
//                $row->delete();
//                // Update the peak memory usage
//                $peakMemoryUsage = max($peakMemoryUsage, memory_get_peak_usage());
//            }

//            foreach (BranchEntry::where('project_id', $projectId)->cursor() as $row) {
//                // Delete the row
//                $row->delete();
//                // Update the peak memory usage
//                $peakMemoryUsage = max($peakMemoryUsage, memory_get_peak_usage());
//            }


            $finalMemoryUsage = memory_get_usage();
            $memoryUsed = $finalMemoryUsage - $initialMemoryUsage;

            $initialMemoryUsage = Common::formatBytes($initialMemoryUsage);
            $finalMemoryUsage = Common::formatBytes($finalMemoryUsage);
            $memoryUsed = Common::formatBytes($memoryUsed);
            $peakMemoryUsage = Common::formatBytes($peakMemoryUsage);

            // Log memory usage details
            Log::info("Memory Usage for Deleting Entries");
            Log::info("Initial Memory Usage: " . $initialMemoryUsage);
            Log::info("Final Memory Usage: " . $finalMemoryUsage);
            Log::info("Memory Used: " . $memoryUsed);
            Log::info("Peak Memory Usage: " . $peakMemoryUsage);
            return true;
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollBack();
            return false;
        }
    }

    public function archiveUser($email, $userId): bool
    {
        try {
            DB::beginTransaction();
            $user = User::where('email', $email)
                ->where('id', $userId)
                ->where('state', '<>', 'archived')
                ->first();

            //if any role, remove them manually,
            //this happens when the project has entries and the user needs to be archived not deleted
            $roles = ProjectRole::where('user_id', $userId);
            /*
             * This expression will ensure that $areRolesDeleted
            is true if either there are no roles ($roles->count() is zero)
            or if the deletion operation is successful.
            */
            $areRolesDeleted = !($roles->count() > 0) || $roles->delete();

            //remove all user providers manually
            //at least 1 user provider is always present if a user has authenticated...
            $providers = UserProvider::where('user_id', $userId);
            $areProvidersDeleted = !($providers->count() > 0) || $providers->delete();

            //archive user by anonymizing row
            $user->state = 'archived';
            $user->name = '-';
            $user->last_name = '-';
            //assign a fake unique value to email field(email has a unique index constraint)
            $user->email = Generators::archivedUserEmail();
            $user->remember_token = ' ';
            $user->api_token = ' ';

            if ($user->save() && $areRolesDeleted && $areProvidersDeleted) {
                DB::commit();
                return true;
            } else {
                DB::rollBack();
                return false;
            }
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }
}
