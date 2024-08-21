<?php namespace ec5\Libraries\Jwt;

use Firebase\JWT\Key;
use Firebase\JWT\JWT as FirebaseJwt;
use Exception;

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
    /**
     * @var array
     */
    private $errors = [];

    /**
     * Jwt constructor.
     */
    public function __construct()
    {
        //
    }

    /**
     * Return the errors array.
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Check if any errors.
     *
     * @return boolean
     */
    public function hasErrors()
    {
        return count($this->errors) > 0 ? true : false;
    }

    /**
     * Generate a JWT token.
     *
     * @param $apiToken
     * @return array JWT Token
     */
    public function generateToken($apiToken)
    {

        $token = null;

        // get auth jwt config settings
        $jwtConfig = config('auth.jwt');

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
            $token = FirebaseJwt::encode(
                $data,      // Data to be encoded in the JWT
                $secretKey, // The signing key
                'HS256' // The signing algorithm
            );

            return $token;

        } catch (\Throwable $e) {
            $this->errors = ['ec5_50'];
        }

        return $token;

    }

    public function generatePasswordlessToken($apiToken)
    {
        $token = null;

        // get auth jwt config settings
        $jwtConfig = config('auth.jwt-passwordless');

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
            $token = FirebaseJwt::encode(
                $data,      // Data to be encoded in the JWT
                $secretKey, // The signing key
                'HS256' // The signing algorithm
            );

            return $token;

        } catch (\Throwable $e) {
            $this->errors = ['ec5_50'];
        }

        return $token;

    }

    /**
     * Verify a JWT token.
     *
     * @param $token
     * @param bool $returnClaim
     * @return array|bool
     */
    public function verifyToken($token, $returnClaim = false)
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
            $decodedToken = (array)FirebaseJwt::decode(
                $token,
                new Key($secretKey, 'HS256')
            );

            // token verified
            if ($returnClaim) {
                return $decodedToken;
            }
            return true;

        } catch (\Throwable $e) {

            // Token invalid:
            // Signature not valid, jwt token expired or altered
            $this->errors = ['ec5_51'];
            return false;
        }

    }

    /**
     * Generate a unique id to store against a odel.
     *
     * @param int $id
     * @return string
     */
    public function generateApiToken(int $id): string
    {
        // Generate unique id
        $apiToken = uniqid($id . '-');

        return $apiToken;

    }

    /**
     * Get the subject part of the claim, while also verifying the jwt token supplied
     *
     * @param $token
     * @return bool|string
     */
    public function getSubject($token)
    {
        $claim = $this->verifyToken($token, true);

        // If we have a claim, return the subject
        if ($claim && isset($claim->sub)) {
            return $claim->sub;
        }

        return null;
    }

}
