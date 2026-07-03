<?php

namespace ec5\Models\OAuth;

use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property string $name
 * @property string $secret
 * @property string $redirect
 * @property int $personal_access_client
 * @property int $password_client
 * @property int $revoked
 * @property array $grant_types
 * @property array $redirect_uris
 */
class OAuthClient extends Model
{
    use SerializeDates;

    protected $table = 'oauth_clients';

    protected $casts = [
        'grant_types' => 'array',
        'redirect_uris' => 'array',
        'personal_access_client' => 'bool',
        'password_client' => 'bool',
        'revoked' => 'bool',
    ];
}
