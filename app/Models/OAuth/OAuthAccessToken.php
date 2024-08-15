<?php

namespace ec5\Models\OAuth;

use DateTimeInterface;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class OAuthAccessToken extends Model
{
    use SerializeDates;

    protected $table = 'oauth_access_tokens';
}
