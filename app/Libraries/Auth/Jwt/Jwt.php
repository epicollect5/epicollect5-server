<?php

namespace ec5\Libraries\Auth\Jwt;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Log;
use Throwable;

class Jwt
{
    /*
    |--------------------------------------------------------------------------
    | Jwt class
    |--------------------------------------------------------------------------
    |
    | This class handles the generating and verifying of JWT tokens
    |
    */
    private array $errors = [];

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Generate a JWT token.
     */
    public function generateToken(string $apiToken): ?string
    {
        // get auth jwt config settings
        $jwtConfig = config('auth.jwt');
        return $this->buildJWTToken($jwtConfig, $apiToken);
    }

    public function generatePasswordlessToken(string $apiToken): ?string
    {
        // get auth jwt config settings
        $jwtConfig = config('auth.jwt-passwordless');
        return $this->buildJWTToken($jwtConfig, $apiToken);
    }

    /**
     * Verify a JWT token (valid, not expired, etc.)
     */
    public function verifyToken($token, bool $returnClaim = false): bool|array
    {
        /**
         * IMPORTANT:
         * You must specify supported algorithms for your application. See
         * https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
         * for a list of spec-compliant algorithms.
         */

        // Get auth jwt config settings
        $jwtConfig = config('auth.jwt');
        $secretKey = $jwtConfig['secret_key'];

        // Attempt to decode the jwt token
        try {
            //imp exception is thrown when token is not valid or expired
            $decodedToken = (array)FirebaseJwt::decode(
                $token,
                new Key($secretKey, 'HS256')
            );

            // token verified
            if ($returnClaim) {
                return $decodedToken;
            }
            return true;

        } catch (Throwable $e) {
            Log::info(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            // Token invalid:
            // Imp: Signature not valid, jwt token expired or altered
            $this->errors = ['ec5_51'];
            return false;
        }
    }

    /**
     * Generate a unique id to store against a model.
     *
     * @param int $id
     * @return string
     */
    public function generateApiToken(int $id): string
    {
        // Generate unique id
        return uniqid($id . '-');
    }

    /**
     * Get the subject part of the claim, while also verifying the jwt token supplied
     *
     * @param $token
     * @return bool|string|null
     */
    public function getSubject($token): bool|string|null
    {
        $claim = $this->verifyToken($token, true);

        // If we have a claim, return the subject
        if ($claim && isset($claim['sub'])) {
            return $claim['sub'];
        }

        return null;
    }

    private function buildJWTToken(array $jwtConfig, string $apiToken): ?string
    {
        try {
            // Extract the key, from the config file.
            $secretKey = $jwtConfig['secret_key'];
            $expiryTime = time() + $jwtConfig['expire'];

            $data = array(
                'iat' => time(), // issued at time
                'jti' => uniqid(),//$apiToken, // unique token id
                'iss' => config('app.url'), // issuer
                'exp' => $expiryTime, // expiry time
                'sub' => $apiToken, // subject i.e. user token
            );

            // Encode the array to a JWT string.
            return FirebaseJwt::encode(
                $data,      // Data to be encoded in the JWT
                $secretKey, // The signing key
                'HS256' // The signing algorithm
            );

        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            $this->errors = ['ec5_50'];
        }

        return null;
    }
}
