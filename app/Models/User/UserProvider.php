<?php

namespace ec5\Models\User;

use DateTimeInterface;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class UserProvider extends Model
{

    use SerializeDates;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users_providers';
}
