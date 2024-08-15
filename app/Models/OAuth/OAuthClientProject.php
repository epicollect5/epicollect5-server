<?php

namespace ec5\Models\OAuth;

use DateTimeInterface;
use DB;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;
use Log;

/**
 * @property int $id
 * @property int $project_id
 * @property int $client_id
 * @property string $created_at
 * @property string $updated_at
 */
class OAuthClientProject extends Model
{
    use SerializeDates;

    protected $table = 'oauth_client_projects';

    public static function getApps($projectId)
    {
        return self::join(config('epicollect.tables.oauth_clients'),
            'oauth_client_projects.client_id', '=', 'oauth_clients.id')
            ->where('oauth_client_projects.project_id', '=', $projectId)
            ->select([
                'oauth_clients.name',
                'oauth_clients.id',
                'oauth_clients.secret',
                'oauth_clients.created_at'
            ])->get();
    }

    public static function removeApp($projectId, $clientId): bool
    {
        try {
            DB::beginTransaction();
            // Delete client
            DB::table(config('epicollect.tables.oauth_clients'))
                ->where('id', '=', $clientId)
                ->delete();

            // Delete project client
            self::where('client_id', '=', $clientId)
                ->where('project_id', '=', $projectId)
                ->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    /**
     * @param $clientId
     * @param $projectId
     * @return boolean
     */
    public static function doesExist($clientId, $projectId): bool
    {
        return self::where('project_id', $projectId)
            ->where('client_id', $clientId)
            ->exists();
    }


}
