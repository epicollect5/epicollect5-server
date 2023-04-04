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

class UserPasswordlessWeb extends Model
{

    const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users_passwordless_web';

    protected $fillable = [];

    public function isValidToken($decodedSent)
    {
        //decode
        $jwtConfig = Config::get('auth.jwt-passwordless');
        $secretKey = $jwtConfig['secret_key'];

        try {
            $decodedStored = (array)FirebaseJwt::decode($this->attributes['token'], $secretKey, array('HS256'));
        } catch (Exception $e) {
            Log::error('Error decoding jwt-passwordless', ['exception' => $e->getMessage()]);
            return false;
        }

        //is the token uuid the same?
        if ($decodedStored['jti'] ===  $decodedSent['jti']) {
            //is the token timestamp still valid?
            if (Carbon::parse($this->attributes['expires_at'])->greaterThan(Carbon::now())) {
                return true;
            } else {
                Log::error('jwt passwordless web expired');
            }
        }

        return false;
    }

    public function isValidCode($code)
    {
        //is the code the same?
        if (Hash::check($code, $this->attributes['token'])) {
            //is the code timestamp still valid?
            if (Carbon::parse($this->attributes['expires_at'])->greaterThan(Carbon::now())) {
                //are there any attempts left
                if ($this->attributes['attempts'] > 0) {
                    return true;
                }
            }
        }
        //decrement attempts
        $this->decrement('attempts');

        return false;
    }
}
