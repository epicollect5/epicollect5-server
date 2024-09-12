<?php

namespace ec5\Traits\Eloquent;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Illuminate\Support\Facades\DB;
use Log;
use Throwable;

trait Archiver
{
    /* Archive a project by setting its row as archived.

    All roles are deleted from the project_roles table

    All the other tables with the project_id constraint are left untouched

    A scheduled task can remove archived projects one at a time
    if the number of entries is low, for high number of entries we can
    delete them in batches

    Needs to be tested on a production server
    */
    /**
     * @throws Throwable
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
            $meaninglessUniqueString = Generators::projectRef();
            $project->name = $meaninglessUniqueString;
            $project->slug = $meaninglessUniqueString;
            //reset all other columns to generic values
            $project->small_description = 'Project was archived';
            $project->description = '';
            $project->logo_url = '';
            $project->access = config('epicollect.strings.project_access.private');
            $project->visibility = config('epicollect.strings.project_visibility.hidden');
            $project->category = config('epicollect.strings.project_categories.general');
            $project->can_bulk_upload = config('epicollect.strings.can_bulk_upload.nobody');

            //remove all the roles, otherwise the project would still appear for non-creator roles
            $rolesDeleted = ProjectRole::where('project_id', $projectId)->delete();

            if ($project->save() && $rolesDeleted) {
                DB::commit();
                return true;
            } else {
                DB::rollBack();
                return false;
            }
        } catch (Throwable $e) {
            Log::error('Error archiveProject()', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    /**
     * @throws Throwable
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
            $user->remember_token = '';//it will get hashed
            $user->avatar = '';
            $user->api_token = '';

            if ($user->save() && $areRolesDeleted && $areProvidersDeleted) {
                DB::commit();
                return true;
            } else {
                DB::rollBack();
                return false;
            }
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }
}
