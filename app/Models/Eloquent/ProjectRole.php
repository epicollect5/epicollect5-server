<?php

namespace ec5\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use DB;

class ProjectRole extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'project_roles';

    public $timestamps = false;

    /**
     * Delete all rows having the passed role but skipping the current logged in user
     * Useful when a manager wants to remove all the other managers but not himself
     *
     * @param $projectId
     * @param $role
     * @param $user
     * @return bool|int
     */
    public function deleteByRole($projectId, $role, $user)
    {
        try {
            $affectedRows = DB::table($this->table)
                ->where('project_id', '=', $projectId)
                ->where('user_id', '<>', $user->id)
                ->where('role', '=', $role)
                ->delete();

            return $affectedRows;
        } catch (\Exception $e) {
            \Log::error('Exception removing users by role in bulk',  [
                'projectId' => $projectId,
                'exception' => $e
            ]);

            return -1;
        }
    }

    public function switchUserRole($projectId, $user, $currentRole, $newRole)
    {
        try {
            $affectedRows = DB::table($this->table)
                ->where('project_id', '=', $projectId)
                ->where('user_id', '=', $user->id)
                ->where('role', '=', $currentRole)
                ->update(['role' => $newRole]);

            return $affectedRows;
        } catch (\Exception $e) {
            \Log::error('Exception switching user role',  [
                'projectId' => $projectId,
                'exception' => $e
            ]);

            return -1;
        }
    }

    public function getCountByRole($projectId)
    {
        return DB::table($this->table)
            ->selectRaw('role, count(*) as total')
            ->where('project_id', '=', $projectId)
            ->groupBy('role')
            ->get()
            ->keyBy('role')
            ->toArray();
    }
    public function getCountOverlall($projectId)
    {
        return DB::table($this->table)
            ->selectRaw('count(*) as total')
            ->where('project_id', '=', $projectId)
            ->get()
            ->first();
    }
}
