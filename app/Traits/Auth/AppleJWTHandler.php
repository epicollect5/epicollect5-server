<?php

namespace ec5\Traits\Auth;

use ec5\Libraries\Auth\JwtApple\JWK as JWKApple;
use ec5\Libraries\Auth\JwtApple\JWT as JWTApple;
use Log;
use Throwable;

trait AppleJWTHandler
{
    public function parseIdentityToken($identityToken): ?array
    {
        //get public keys from Apple endpoint
        list($jwks, $kid) = $this->getPublicKeysFromAppleEndpoint($identityToken);

        //build jwt public key using keys from Apple endpoint (using JWK)
        try {
            $public_key = JWKApple::parseKeySet($jwks);
            $public_key = $public_key[$kid];
            $parsed_id_token = JWTApple::decode($identityToken, $public_key, ['RS256']);
        } catch (Throwable $e) {
            Log::error('Apple Sign In JWT Error', ['exception' => $e->getMessage()]);
            //we get here when there is any validation error
            return null;
        }
        return (array)$parsed_id_token;
    }

    public function getPublicKeysFromAppleEndpoint(mixed $token): array
    {
        $apple_jwk_keys = json_decode(file_get_contents(config('auth.apple.public_keys_endpoint')), null, 512);
        $keys = array();
        foreach ($apple_jwk_keys->keys as $key) {
            $keys[] = (array)$key;
        }
        $jwks = ['keys' => $keys];

        //get kid from jwy header
        $header_base_64 = explode('.', $token ?? '')[0];
        $kid = JWTApple::jsonDecode(JWTApple::urlsafeB64Decode($header_base_64));
        $kid = $kid->kid;
        return array($jwks, $kid);
    }
}
