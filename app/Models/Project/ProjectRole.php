<?php

namespace ec5\Models\Project;

use DB;
use ec5\Models\User\User;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;
use Log;
use Throwable;

/**
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $role
 */
class ProjectRole extends Model
{
    use SerializeDates;

    protected $table = 'project_roles';
    protected $fillable = ['project_id', 'user_id', 'role'];

    public $timestamps = false;

    /**
     * Delete all rows having the passed role but skipping the current logged-in user
     * Useful when a manager wants to remove all the other managers but not himself
     *
     * @param $projectId
     * @param $role
     * @param $user
     * @return int
     */
    public function deleteByRole($projectId, $role, $user): int
    {
        try {
            return DB::table($this->table)
                ->where('project_id', '=', $projectId)
                ->where('user_id', '<>', $user->id)
                ->where('role', '=', $role)
                ->delete();
        } catch (Throwable $e) {
            Log::error('Exception removing users by role in bulk', [
                'projectId' => $projectId,
                'exception' => $e
            ]);

            return -1;
        }
    }

    public function switchUserRole($projectId, $user, $currentRole, $newRole): int
    {
        try {
            return DB::table($this->table)
                ->where('project_id', '=', $projectId)
                ->where('user_id', '=', $user->id)
                ->where('role', '=', $currentRole)
                ->update(['role' => $newRole]);
        } catch (Throwable $e) {
            Log::error('Exception switching user role', [
                'projectId' => $projectId,
                'exception' => $e
            ]);

            return -1;
        }
    }

    public function getCountByRole($projectId): array
    {
        return DB::table($this->table)
            ->selectRaw('role, count(*) as total')
            ->where('project_id', '=', $projectId)
            ->groupBy('role')
            ->get()
            ->keyBy('role')
            ->toArray();
    }

    public function getCountOverall($projectId)
    {
        return DB::table($this->table)
            ->selectRaw('count(*) as total')
            ->where('project_id', '=', $projectId)
            ->get()
            ->first();
    }

    public static function getAllProjectMembers($projectId): array
    {
        // Get all users belonging to this project
        $projectRoles = DB::table(config('epicollect.tables.project_roles'))
            ->where('project_id', $projectId)->get();
        $users = [];
        foreach ($projectRoles as $index => $projectRole) {
            $users[$index] = User::where('id', $projectRole->user_id)->first();
            $users[$index]['role'] = $projectRole->role;
        }
        return $users;
    }
}
