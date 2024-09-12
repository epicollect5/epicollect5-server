<?php

namespace ec5\Models\User;

use Carbon\Carbon;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class UserProvider extends Model
{
    /**
     * @property int $id
     * @property int $user_id
     * @property string $email
     * @property string $provider
     * @property Carbon $created_at
     * @property Carbon $updated_at
     */

    use SerializeDates;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users_providers';
}
