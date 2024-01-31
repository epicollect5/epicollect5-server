<?php

namespace ec5\Models\User;

use Carbon\Carbon;
use Hash;
use Illuminate\Database\Eloquent\Model;

class UserVerify extends Model
{

    const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users_verify';

    protected $guarded = [];

    public function isValidCode($code)
    {
        //is the code the same?
        if (Hash::check($code, $this->attributes['code'])) {
            //is the code timestamp still valid?
            if (Carbon::parse($this->attributes['expires_at'])->greaterThan(Carbon::now())) {
                return true;
            }
        }
        return false;
    }
}


