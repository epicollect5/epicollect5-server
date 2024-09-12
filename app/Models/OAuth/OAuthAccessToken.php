<?php

namespace ec5\Models\OAuth;

use Carbon\Carbon;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class OAuthAccessToken extends Model
{
    /**
     * @property int $id
     * @property int|null $user_id
     * @property int $client_id
     * @property string|null $name
     * @property string|null $scopes
     * @property int $revoked
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property string|null $expires_at
     */

    use SerializeDates;

    protected $table = 'oauth_access_tokens';
}
