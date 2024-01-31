<?php

namespace ec5\Models\OAuth;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property string $name
 * @property string $secret
 * @property string $redirect
 * @property int $personal_access_client
 * @property int $password_client
 * @property int $revoked
 */
class OAuthClient extends Model
{
    protected $table = 'oauth_clients';
}
