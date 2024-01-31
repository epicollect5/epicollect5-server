<?php

namespace ec5\Models\User;

use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT as FirebaseJWT;
use Illuminate\Database\Eloquent\Model;
use Log;

class UserResetPassword extends Model
{

    const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users_reset_password';

    protected $fillable = [];

    public function isValidToken($decodedSent)
    {
        //decode
        $jwtConfig = config('auth.jwt-forgot');
        $secretKey = $jwtConfig['secret_key'];

        try {
            $decodedStored = (array)FirebaseJwt::decode($this->attributes['token'], $secretKey, array('HS256'));
        } catch (Exception $e) {
            Log::error('Error decoding jwt-forgot', ['exception' => $e->getMessage()]);
            return false;
        }

        //is the token uuid the same?
        if ($decodedStored['jti'] === $decodedSent['jti']) {
            //is the token timestamp still valid?
            if (Carbon::parse($this->attributes['expires_at'])->greaterThan(Carbon::now())) {
                return true;
            }
        }
        return false;
    }
}


