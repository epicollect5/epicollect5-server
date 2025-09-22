<?php

namespace ec5\Models\Project;

use Carbon\Carbon;
use DB;
use ec5\DTO\ProjectDTO;
use ec5\Traits\Models\SerializeDates;
use Exception;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Log;
use Throwable;

class Project extends Model
{
    /**
     * @property int $id
     * @property string $name
     * @property string $slug
     * @property string $ref
     * @property string $description
     * @property string $small_description
     * @property string $logo_url
     * @property string $access
     * @property string $visibility
     * @property string $category
     * @property int $created_by
     * @property Carbon $created_at
     * @property Carbon $updated_at
     * @property string $status
     * @property string $can_bulk_upload
     * @property string $app_link_visibility
     */
    use SerializeDates;

    protected $table = 'projects';
    protected string $projectStatsTable = 'project_stats';
    protected $fillable = ['slug'];

    /**
     * Casting to a datetime ISO 8601 without milliseconds
     * due to legacy reasons
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    //used to init ProjectDTO, returns a bundle with data from multiple tables
    public static function findBySlug($slug)
    {
        $query = DB::table(config('epicollect.tables.projects'));
        $query = $query->where('projects.slug', $slug);
        // Skip rows where status is 'archived'
        $query = $query->where('status', '<>', 'archived');

        $query = $query->leftJoin(
            config('epicollect.tables.project_stats'),
            'projects.id',
            '=',
            'project_stats.project_id'
        );

        $query = $query->leftJoin(
            config('epicollect.tables.project_structures'),
            'projects.id',
            '=',
            'project_structures.project_id'
        );

        $query = $query->select(
            'projects.*',
            'project_stats.id AS stats_id',
            'project_stats.*',
            'project_structures.*',
            DB::raw('DATE_FORMAT(project_structures.updated_at, "%Y-%m-%d %H:%i:%s") as structure_last_updated'),
            DB::raw('DATE_FORMAT(projects.created_at, "%Y-%m-%d %H:%i:%s") as created_at'),
            DB::raw('DATE_FORMAT(projects.updated_at, "%Y-%m-%d %H:%i:%s") as updated_at'),
            'project_structures.id as structure_id'
        );

        return $query->first();
    }

    public function myProjects($perPage, $userId, $params): Paginator
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

    public function publicAndListed($category = null, $params = []): Paginator
    {
        // Define constants
        $trashedStatus = config('epicollect.strings.project_status.trashed');
        $archivedStatus = config('epicollect.strings.project_status.archived');
        $publicAccess = config('epicollect.strings.project_access.public');
        $listedVisibility = config('epicollect.strings.project_visibility.listed');
        $projectsPerPage = config('epicollect.limits.projects_per_page');
        $sortBy = config('epicollect.mappings.search_projects_defaults.sort_by');
        $sortOrder = config('epicollect.mappings.search_projects_defaults.sort_order');

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

    public function featured(): Collection|array
    {
        return Project::join(config('epicollect.tables.projects_featured'), 'projects.id', '=', config('epicollect.tables.projects_featured') . '.project_id')
            ->orderBy('projects_featured.id', 'asc')
            ->get();
    }

    public static function creatorEmail($projectId)
    {
        $email = 'n/a';
        try {
            return Project::join(config('epicollect.tables.users'), 'projects.created_by', config('epicollect.tables.users') . '.id')
                ->where('projects.id', $projectId)
                ->first()->email;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return $email;
        }
    }

    public static function version($slug): string
    {
        $updatedAt = Project::join(config('epicollect.tables.project_structures'), 'projects.id', '=', config('epicollect.tables.project_structures') . '.project_id')
            ->where('projects.slug', $slug)
            //imp: this nis needed to drop the milliseconds (pre Laravel 7 behaviour)
            ->select(DB::raw("DATE_FORMAT(project_structures.updated_at, '%Y-%m-%d %H:%i:%s') as updated_at"))
            ->pluck('updated_at')
            ->first();
        //cast to ISO string since it is a Carbon instance now
        return (string)$updatedAt;
    }

    public function admin($params = []): Paginator|array
    {

        $perPage  = config('epicollect.limits.admin_projects_per_page');
        return $this
            ->join($this->projectStatsTable, $this->getTable() . '.id', '=', $this->projectStatsTable . '.project_id')
            ->leftJoin('users', $this->getTable() . '.created_by', '=', 'users.id')  // Join users table
            ->where(function ($query) use ($params) {
                if (!empty($params['name'])) {
                    $query->where($this->getTable() . '.name', 'LIKE', '%' . $params['name'] . '%'); // Restore search by name
                }
            })
            ->where(function ($query) use ($params) {
                if (!empty($params['access'])) {
                    $query->where($this->getTable() . '.access', '=', $params['access']);
                }
            })
            ->where(function ($query) use ($params) {
                if (!empty($params['visibility'])) {
                    $query->where($this->getTable() . '.visibility', '=', $params['visibility']);
                }
            })
            ->where($this->getTable() . '.status', '<>', 'archived')
            ->orderBy($this->projectStatsTable . '.total_entries', 'desc')  // Ensure sorting works
            ->select(
                $this->getTable() . '.*',
                'users.name as user_name',
                'users.last_name as user_last_name',
                $this->projectStatsTable . '.total_entries', // Explicitly select total_entries
                $this->projectStatsTable . '.total_bytes', // Explicitly select total_bytes
            )
            ->simplePaginate($perPage);
    }


    /**
     * @throws Throwable
     */
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
        } catch (Throwable $e) {
            // If any exceptions, log
            Log::error('Transfer ownership failed: ', [
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
    public static function startsWith($name, $columns = ['*']): Collection|array
    {
        $trashedStatus = config('epicollect.strings.project_status.trashed');
        $archivedStatus = config('epicollect.strings.project_status.archived');

        return static::select($columns)
        ->where('name', 'like', $name . '%')
        ->where('status', '<>', $trashedStatus)
        ->where('status', '<>', $archivedStatus)
        ->orderByRaw('LOWER(name) = ? DESC', [strtolower($name)]) // Exact match first
        ->orderBy('updated_at', 'desc') // Optional: sort the rest by updated_at
        ->take(50)
        ->get();
    }

    public static function matches($name, $columns = ['*']): Collection|array
    {
        $trashedStatus = config('epicollect.strings.project_status.trashed');
        $archivedStatus = config('epicollect.strings.project_status.archived');

        return static::select($columns)
            ->where('name', '=', $name)
            ->where('status', '<>', $trashedStatus)
            ->where('status', '<>', $archivedStatus)
            ->take(1)
            ->get();
    }

    /**
     * @throws Throwable
     */
    public static function updateAllTables(ProjectDTO $project, $params, $setUpdatedAt = false): bool
    {
        try {
            DB::beginTransaction();
            //update project table
            $didProjectUpdate = self::where('id', $project->getId())->update($params);
            //update project_structures table
            $didStructureUpdate = ProjectStructure::updateStructures($project, $setUpdatedAt);

            if ($didProjectUpdate && $didStructureUpdate) {
                DB::commit();
                return true;
            } else {
                throw new Exception('Could not update project tables');
            }
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

}
