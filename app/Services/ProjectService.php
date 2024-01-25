<?php

namespace ec5\Services;

use DB;
use ec5\DTO\ProjectRoleDTO as LegacyProjectRole;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStats;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Eloquent\User;
use ec5\Models\Projects\Project as LegacyProject;
use Exception;
use Log;

class ProjectService
{
    public function storeProject(LegacyProject $project)
    {
        try {
            DB::beginTransaction();

            $projectDefinition = $project->getProjectDefinition();
            $projectExtra = $project->getProjectExtra();
            $projectMapping = $project->getProjectMapping();
            $projectStats = $project->getProjectStats();
            $projectDetails = $project->getProjectDetails();
            $projectRef = $projectDetails['ref'] ?? null;

            if (empty($projectRef)) {
                throw new Exception('Project ref is empty');
            }

            $projectStored = new Project();
            $projectStored->name = $projectDetails['name'];
            $projectStored->slug = $projectDetails['slug'];
            $projectStored->ref = $projectDetails['ref'];
            $projectStored->description = $projectDetails['description'];
            $projectStored->small_description = $projectDetails['small_description'];
            $projectStored->logo_url = $projectDetails['logo_url'];
            $projectStored->access = $projectDetails['access'];
            $projectStored->visibility = $projectDetails['visibility'];
            $projectStored->category = $projectDetails['category'];
            $projectStored->created_by = $projectDetails['created_by'];
            $projectStored->can_bulk_upload = $projectDetails['can_bulk_upload'];
            $didSaveProject = $projectStored->save();

            if (!$didSaveProject) {
                throw new Exception('Project could not be saved');
            }

            //add the project role
            $projectRoleStored = new ProjectRole([
                'project_id' => $projectStored->id,
                'user_id' => $projectStored->created_by,
                'role' => config('epicollect.strings.project_roles.creator')
            ]);
            $didSaveProjectRole = $projectRoleStored->save();
            if (!$didSaveProjectRole) {
                throw new Exception('Project role could not be saved');
            }

            // Insert project stats
            $statsData = $projectStats->getJsonData();
            $statsData['project_id'] = $projectStored->id;

            // Insert project details get back insert_id
            $projectStatsStored = new ProjectStats($statsData);
            $didSaveProjectStats = $projectStatsStored->save();

            if (!$didSaveProjectStats) {
                throw new Exception('Project stats could not be saved');
            }

            // Insert project JSON structures
            $structureData['project_id'] = $projectStored->id;
            $structureData['project_definition'] = $projectDefinition->getJsonData();
            $structureData['project_extra'] = $projectExtra->getJsonData();
            $structureData['project_mapping'] = $projectMapping->getJsonData();

            $projectStructureStored = new ProjectStructure($structureData);
            $didSaveProjectStructure = $projectStructureStored->save();

            if (!$didSaveProjectStructure) {
                throw new Exception('Project structure could not be saved');
            }
            // All good
            DB::commit();
            return $projectStored->id;
        } catch (Exception  $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return 0;
        }
    }

    /**
     * Function for retrieving paginated project users
     * Optional search criteria can be passed through
     * This will either return an array of sets of users
     * indexed 'manager', 'curator', 'collector',
     * or just the users for one set.
     *
     * @param int $perPage
     * @param string $search
     * @param array $params
     * @return array
     */
    public function getProjectMembersPaginated($perPage = 1, $search = '', $params = array()): array
    {
        $users = [];

        // loop round and gather users for each set of roles
        foreach ($params['roles'] as $role) {

            // retrieve all project users where role is $role
            $userSet = DB::table(config('epicollect.tables.users'))
                ->select('users.id', 'users.name', 'users.last_name', 'users.email', 'project_roles.role')
                ->join('project_roles', 'users.id', '=', 'project_roles.user_id')
                ->where('project_roles.project_id', '=', $params['project_id'])
                ->where('project_roles.role', '=', $role)
                ->where(function ($query) use ($search) {
                    // if we have search criteria, add to where clause
                    if (!empty($search)) {
                        $query->where('users.name', 'LIKE', '%' . $search . '%')
                            ->orWhere('users.email', 'LIKE', '%' . $search . '%');
                    }
                })
                ->orderBy('users.name', 'asc');

            // now paginate users
            // setting the 'page' variable name relative to the $role
            $users[$role] = $userSet->simplePaginate($perPage, ['*'], 'page-' . $role);
        }
        return $users;
    }

    /**
     * Populate and return a ProjectRole object for user/project
     *
     * @param User|null $user
     * @param $projectId
     * @return LegacyProjectRole
     */
    public function getRole($user = null, $projectId): LegacyProjectRole
    {
        $projectRoleUser = new LegacyProjectRole();
        $role = null;
        // If we have a valid user
        if ($user && $user->id) {
            $projectRole = DB::table('project_roles')
                ->select('role')
                ->where('user_id', '=', $user->id)
                ->where('project_id', '=', $projectId)
                ->first();
            // If a project role is found, set
            if ($projectRole) {
                $role = $projectRole->role;
            }

        }
        // Set the project role and return
        $projectRoleUser->setRole($user, $projectId, $role);
        return $projectRoleUser;
    }

    public function cloneProjectRoles($projectIdFrom, $projectIdTo): bool
    {
        try {
            DB::beginTransaction();
            //remove all roles from destination project
            //there should be only the CREATOR role at this point
            ProjectRole::where('project_id', $projectIdTo)->delete();

            //copy roles from the source project
            $projectRoles = ProjectRole::where('project_id', $projectIdFrom)->get();
            foreach ($projectRoles as $projectRole) {
                // Try and insert to the destination project
                $row = new ProjectRole([
                    'user_id' => $projectRole->user_id,
                    'project_id' => $projectIdTo,
                    'role' => $projectRole->role
                ]);
                $roleSaved = $row->save();
                if (!$roleSaved) {
                    throw new Exception('Cannot save role');
                }
            }

            DB::commit();
            return true;
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    //Only add the user if it is not a member of the project
    public function addOrUpdateUserRole($userId, $projectId, $role): bool
    {
        try {
            DB::beginTransaction();
            $userToAddRole = ProjectRole::where('user_id', $userId)
                ->where('project_id', $projectId)
                ->first();

            if ($userToAddRole) {
                //the user with the provided email is already a member of the project
                //just update its role as it has passed the validation above
                $wasUserUpdated = $userToAddRole->update(['role' => $role]);
                if (!$wasUserUpdated) {
                    throw new Exception('Cannot add or update user role ');
                }

            } else {
                // Create the project role for this user
                $projectRole = new ProjectRole([
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'role' => $role
                ]);
                $wasUserAdded = $projectRole->save();
                if (!$wasUserAdded) {
                    throw new Exception('Cannot add or update user role ');
                }
                DB::commit();
                return true;
            }
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }

        return true;
    }
}