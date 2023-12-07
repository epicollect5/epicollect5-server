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
            ->leftJoin(config('epicollect.tables.project_roles'), $this->getQualifiedKeyName(), '=', 'project_roles.project_id')
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
        $trashedStatus = config('epicollect.strings.project_status.trashed');
        $archivedStatus = config('epicollect.strings.project_status.archived');
        $publicAccess = config('epicollect.strings.project_access.public');
        $listedVisibility = config('epicollect.strings.project_visibility.listed');
        $projectsPerPage = config('epicollect.limits.projects_per_page');
        $sortBy = config('epicollect.strings.search_projects_defaults.sort_by');
        $sortOrder = config('epicollect.strings.search_projects_defaults.sort_order');

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
        return Project::join(config('epicollect.tables.projects_featured'), 'projects.id', '=', config('epicollect.tables.projects_featured') . '.project_id')
            ->orderBy('projects_featured.id', 'asc')
            ->get();
    }

    public static function creatorEmail($projectId)
    {
        return Project::join(config('epicollect.tables.users'), 'projects.created_by', config('epicollect.tables.users') . '.id')
            ->where('projects.id', $projectId)
            ->first()->email;
    }

    public static function version($slug)
    {
        return Project::join(config('epicollect.tables.project_structures'), 'projects.id', '=', config('epicollect.tables.project_structures') . '.project_id')
            ->where('projects.slug', $slug)
            ->pluck('project_structures.updated_at')
            ->first();
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

    public function transferOwnership($projectId, $creatorId, $managerId): bool
    {
        try {
            //update projects table, set manager to be new creator
            $project = $this->findOrFail($projectId);
            //set the new manager as creator
            $project->created_by = $managerId;

            //set old Creator as Manager
            $oldCreator = ProjectRole::where('project_id', $projectId)->where('user_id', $creatorId)->firstOrFail();
            $oldCreator->role = config('epicollect.strings.project_roles.manager');

            //set the new Manager as Creator
            $newCreator = ProjectRole::where('project_id', $projectId)->where('user_id', $managerId)->firstOrFail();
            $newCreator->role = config('epicollect.strings.project_roles.creator');

            //try to commit all changes
            DB::transaction(function () use ($project, $oldCreator, $newCreator) {
                $project->save();
                $oldCreator->save();
                $newCreator->save();
            });

            return true;
        } catch (\Exception $e) {
            // If any exceptions, log
            \Log::error('Transfer ownership failed: ', [
                'exception' => $e->getMessage(),
                'project' => $this->requestedProject->name
            ]);

            return false;
        }
    }

    /**
     * Return all the projects which starts the string passed in the name
     * it is used by the mobile app project search
     * limit to 50 to keep it responsive on the mobile app
     * Order by updated_at to list the latest and most active projects first
     * Remember: project names are unique!
     *
     * Trashed or archived projects are skipped
     */
    public static function startsWith($name, $columns = ['*'])
    {
        $trashedStatus = config('epicollect.strings.project_status.trashed');
        $archivedStatus = config('epicollect.strings.project_status.archived');

        return static::select($columns)
            ->where('name', 'like', $name . '%')
            ->where('status', '<>', $trashedStatus)
            ->where('status', '<>', $archivedStatus)
            ->orderBy('updated_at', 'desc')
            ->take(50)
            ->get();
    }
}
