<?php

namespace ec5\Services;

use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStats;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Projects\Project as LegacyProject;
use DB;
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
}