<?php

namespace ec5\Traits\Eloquent;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\Eloquent\Entries\BranchEntry;
use ec5\Models\Eloquent\Entries\BranchEntryArchive;
use ec5\Models\Eloquent\Entries\Entry;
use ec5\Models\Eloquent\Entries\EntryArchive;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\User;
use ec5\Models\Eloquent\UserProvider;
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
        try {
            DB::beginTransaction();
            //move entries
            Entry::where('project_id', $projectId)->chunk(100, function ($rowsToMove) {
                foreach ($rowsToMove as $row) {
                    $rowToArchive = $row->replicate();
                    // make into an array for mass assign.
                    $rowToArchive = $rowToArchive->toArray();
                    //create copy to projects_archive table
                    EntryArchive::create($rowToArchive);
                }
            });

            //move branch entries as well
            BranchEntry::where('project_id', $projectId)->chunk(100, function ($rowsToMove) {
                foreach ($rowsToMove as $row) {
                    $rowToArchive = $row->replicate();
                    // make into array for mass assign. 
                    $rowToArchive = $rowToArchive->toArray();
                    //create copy to projects_archive table
                    BranchEntryArchive::create($rowToArchive);
                }
            });

            // All rows have been successfully moved, so you can proceed with deleting the original rows
            Entry::where('project_id', $projectId)->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    $row->delete();
                }
            });

            BranchEntry::where('project_id', $projectId)->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    $row->delete();
                }
            });

            DB::commit();
            return true;
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', [
                'exception' => $e->getMessage()
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
