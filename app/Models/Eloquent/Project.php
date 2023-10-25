<?php

namespace ec5\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use DB;
use Config;

class Project extends Model
{
    protected $table = 'projects';
    protected $projectStatsTable = 'project_stats';
    protected $fillable = ['slug'];

    public function myProjects($perPage, $userId, $params)
    {
        return DB::table($this->getTable())
            ->leftJoin(Config::get('ec5Tables.project_roles'), $this->getQualifiedKeyName(), '=', 'project_roles.project_id')
            ->where('project_roles.user_id', $userId)
            ->where(function ($query) use ($params) {
                if (!empty($params['filter_type']) && !empty($params['filter_value'])) {
                    $query->where($params['filter_type'], '=', $params['filter_value']);
                }
            })
            ->where(function ($query) use ($params) {
                if (!empty($params['search'])) {
                    $query->where('name', 'LIKE', '%' . $params['search'] . '%');
                }
            })
            ->where('status', '<>', 'archived')
            ->orderBy('created_at', 'desc')
            ->simplePaginate($perPage);
    }

    public function publicAndListed($category = null, $params = [])
    {
        // Define constants
        $trashedStatus = Config::get('ec5Strings.project_status.trashed');
        $archivedStatus = Config::get('ec5Strings.project_status.archived');
        $publicAccess = Config::get('ec5Strings.project_access.public');
        $listedVisibility = Config::get('ec5Strings.project_visibility.listed');
        $projectsPerPage = Config::get('ec5Limits.projects_per_page');
        $sortBy = Config::get('ec5Enums.search_projects_defaults.sort_by');
        $sortOrder = Config::get('ec5Enums.search_projects_defaults.sort_order');

        // Base query
        $query = DB::table($this->getTable())->join($this->projectStatsTable, 'projects.id', '=', $this->projectStatsTable . '.project_id')
            ->where('status', '<>', $trashedStatus)
            ->where('status', '<>', $archivedStatus)
            ->where('access', $publicAccess)
            ->where('visibility', $listedVisibility);

        // Filter by name
        if (!empty($params['name'])) {
            $query->where('name', 'LIKE', '%' . $params['name'] . '%');
        }

        // Filter by category if provided
        if ($category) {
            $query->where('category', $category);
        }

        // Sorting
        if (!empty($params['sort_by'])) {
            $sortBy = $params['sort_by'];
        }
        if (!empty($params['sort_order'])) {
            $sortOrder = $params['sort_order'];
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->simplePaginate($projectsPerPage);
    }

    public function featured()
    {
        return Project::join(Config::get('ec5Tables.projects_featured'), 'projects.id', '=', Config::get('ec5Tables.projects_featured') . '.project_id')
            ->orderBy('projects_featured.id', 'asc')
            ->get();
    }

    public function admin($perPage, $params = [])
    {
        return $this->distinct()
            ->join($this->projectStatsTable, $this->getTable() . '.id', '=', $this->projectStatsTable . '.project_id')
            ->where(function ($query) use ($params) {
                if (!empty($params['name'])) {
                    $query->where('name', 'LIKE', '%' . $params['name'] . '%');
                }
            })
            ->where(function ($query) use ($params) {
                if (!empty($params['access'])) {
                    $query->where('access', '=', $params['access']);
                }
            })
            ->where(function ($query) use ($params) {
                if (!empty($params['visibility'])) {
                    $query->where('visibility', '=', $params['visibility']);
                }
            })
            ->where('status', '<>', 'archived')
            ->orderBy('total_entries', 'desc')
            ->simplePaginate($perPage);
    }

    public function findBySlug($slug): Project
    {
        return $this->where('slug', $slug)
            ->where('status', '<>', 'archived')
            ->join($this->projectStatsTable, 'projects.id', '=', 'project_stats.project_id')
            ->join(Config::get('ec5Tables.project_structures'), 'projects.id', '=', 'project_structures.project_id')
            ->select(
                'projects.*',
                'project_stats.id AS stats_id',
                'project_stats.*',
                'project_structures.*',
                'project_structures.updated_at as structure_last_updated',
                'project_structures.id as structure_id'
            )
            ->first();
    }

    public function transferOwnership($projectId, $creatorId, $managerId): bool
    {
        try {
            //update projects table, set manager to be new creator
            $project = $this->findOrFail($projectId);
            //set new manager as creator
            $project->created_by = $managerId;

            //set old Creator as Manager
            $oldCreator = ProjectRole::where('project_id', $projectId)->where('user_id', $creatorId)->firstOrFail();
            $oldCreator->role = Config::get('ec5Permissions.projects.manager_role');

            //set new Manager as Creator
            $newCreator = ProjectRole::where('project_id', $projectId)->where('user_id', $managerId)->firstOrFail();
            $newCreator->role = Config::get('ec5Permissions.projects.creator_role');

            //try to commit all changes
            DB::transaction(function () use ($project, $oldCreator, $newCreator) {
                $project->save();
                $oldCreator->save();
                $newCreator->save();
            });

            return true;
        } catch (\Exception $e) {
            // If any exceptions, log
            EC5Logger::error('Transfer ownership failed', $this->requestedProject, [$e]);
            \Log::error('Transfer ownership failed: ', [
                'exception' => $e->getMessage(),
                'project' => $this->requestedProject->name
            ]);

            return false;
        }
    }
}
