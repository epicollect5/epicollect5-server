<?php

namespace ec5\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Firebase\JWT\JWT as FirebaseJWT;

use DB;
use Carbon\Carbon;
use Hash;
use Config;
use Log;
use Exception;

class UserPasswordlessApi extends Model
{

    const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users_passwordless_api';

    protected $fillable = [];

    public function isValidCode($code)
    {
        //is the code the same?
        if (Hash::check($code, $this->attributes['code'])) {
            //is the code timestamp still valid?
            if (Carbon::parse($this->attributes['expires_at'])->greaterThan(Carbon::now())) {
                //are there any attempts left
                if($this->attributes['attempts'] > 0) {
                    return true;
                }
            }
        }
        //decrement attempts
        $this->decrement('attempts');

        return false;
    }
}


