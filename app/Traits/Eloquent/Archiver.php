<?php

namespace ec5\Traits\Eloquent;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\EntryArchive;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\BranchEntryArchive;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\User;
use ec5\Models\Eloquent\UserProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Log;
use PDOException;

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

            $project->status = Config::get('ec5Strings.project_status.archived');
            //set name and slug to a unique string to avoid duplicates with new projects
            $ref = Generators::projectRef();
            $project->name = $ref;
            $project->slug = $ref;
            //reset all other columns to generic values
            $project->small_description = 'Project was archived';
            $project->description = '';
            $project->logo_url = '';
            $project->access = Config::get('ec5Strings.project_access.private');
            $project->visibility = Config::get('ec5Strings.project_visibility.hidden');
            $project->category = Config::get('ec5Enums.project_categories_icons.general');
            $project->can_bulk_upload = Config::get('ec5Strings.can_bulk_upload.NOBODY');


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
            //move entries
            Entry::where('project_id', $projectId)->chunk(100, function ($rowsToMove) {
                foreach ($rowsToMove as $row) {
                    //todo: check the id AUTO_INCREMENT...
                    $rowToArchive = $row->replicate();
                    // make into array for mass assign. 
                    $rowToArchive = $rowToArchive->toArray();
                    //create copy to projects_archive table
                    EntryArchive::create($rowToArchive);
                }
            });

            //move branch entries as well
            BranchEntry::where('project_id', $projectId)->chunk(100, function ($rowsToMove) {
                foreach ($rowsToMove as $row) {
                    //todo: check the id AUTO_INCREMENT...
                    $rowToArchive = $row->replicate();
                    // make into array for mass assign. 
                    $rowToArchive = $rowToArchive->toArray();
                    //create copy to projects_archive table
                    BranchEntryArchive::create($rowToArchive);
                }
            });

            // All rows have been successfully moved, so you can proceed with deleting the original rows
            Entry::where('project_id', $projectId)->delete();
            BranchEntry::where('project_id', $projectId)->delete();

            return true;
        } catch (\Exception $e) {
            \Log::error('Error soft deleting project entries', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @throws \Exception
     */
    public function archiveUser($email, $userId): bool
    {
        try {
            DB::beginTransaction();
            $user = User::where('email', $email)
                ->where('id', $userId)
                ->where('state', '<>', 'archived')
                ->first();

            //if any role, remove them manually,
            //this happens when the project has entries and the user is archived not deleted
            $roles = ProjectRole::where('user_id', $userId);
            /*
             * This expression will ensure that $areRolesDeleted
            is true if either there are no roles ($roles->count() is zero)
            or if the deletion operation is successful.
            */
            $areRolesDeleted = !($roles->count() > 0) || $roles->delete();

            //remove all user providers manually
            //at least 1 user provider is always present
            $providers = UserProvider::where('user_id', $userId);
            $areProvidersDeleted = $providers->delete();

            //archive user by anonymizing row
            $user->state = 'archived';
            $user->name = '-';
            $user->last_name = '-';
            //assign a fake unique value to email field(email has a unique index constraint)
            $user->email = Generators::projectRef();
            $user->remember_token = ' ';
            $user->api_token = ' ';

            if ($user->save() && $areRolesDeleted && $areProvidersDeleted) {
                DB::commit();
                return true;
            } else {
                DB::rollBack();
                return false;
            }
        } catch (PDOException $e) {
            Log::error('Error archiveUser()', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }
}
