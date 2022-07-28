<?php

namespace ec5\Traits\Auth;

use ec5\Libraries\JwtApple\JWT as JWTApple;
use ec5\Libraries\JwtApple\JWK as JWKApple;
use Log;
use Exception;

trait AppleJWTHandler
{
    public function parseIdentityToken($identityToken)
    {
        $apple_jwk_keys = json_decode(file_get_contents(env('APPLE_PUBLIC_KEYS_ENDPOINT')), null, 512, JSON_OBJECT_AS_ARRAY);
        $keys = array();
        foreach ($apple_jwk_keys->keys as $key) {
            $keys[] = (array)$key;
        }
        $jwks = ['keys' => $keys];

        //get kid from jwy header
        $header_base_64 = explode('.', $identityToken)[0];
        $kid = JWTApple::jsonDecode(JWTApple::urlsafeB64Decode($header_base_64));
        $kid = $kid->kid;

        //build jwt publick key using keys from Apple endpoint (using JWK)
        try {
            $public_key = JWKApple::parseKeySet($jwks);
            $public_key = $public_key[$kid];
            $parsed_id_token = JWTApple::decode($identityToken, $public_key, ['RS256']);
        } catch (Exception $e) {
            Log::error('Apple Sign In JWT Error', ['exception' => $e->getMessage()]);
            //we get here when there is any validation error
            return null;
        }
        return (array)$parsed_id_token;
    }
}
