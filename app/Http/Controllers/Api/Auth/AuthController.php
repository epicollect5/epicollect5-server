<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Libraries\Jwt\JwtUserProvider;
use ec5\Http\Controllers\Controller;
use Config;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Api Authentication Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the authentication of users via the api.
    | Returns a generated JWT token
    |
    */

    protected $provider;
    protected $authMethods;
    protected $appleProviderLabel;
    protected $googleProviderLabel;
    protected $localProviderLabel;
    protected $ldapProviderlabel;
    protected $passwordlessProviderLabel;
    protected $isAuthApiLocalEnabled;

    public function __construct(JwtUserProvider $provider)
    {
        $this->provider = $provider;
        //set providers values
        $this->appleProviderLabel = Config::get('ec5Strings.providers.apple');
        $this->googleProviderLabel = Config::get('ec5Strings.providers.google');
        $this->localProviderLabel = Config::get('ec5Strings.providers.local');
        $this->ldapProviderlabel = Config::get('ec5Strings.providers.ldap');
        $this->passwordlessProviderLabel = Config::get('ec5Strings.providers.passwordless');

        // Determine which authentication methods are available
        $this->authMethods = Config::get('auth.auth_methods');
        $this->isAuthApiLocalEnabled = Config::get('auth.auth_api_local_enabled');
    }

    public function getLogin(ApiResponse $apiResponse)
    {
        $authIds = [];

        // If google is an auth method, supply our Client ID
        if (in_array('google', $this->authMethods)) {
            $providerKey = \Config::get('services.google_api');
            $authIds['google']['CLIENT_ID'] = $providerKey['client_id'];
            $authIds['google']['SCOPE'] = $providerKey['scope'];
        }

        // Return response
        $apiResponse->setData([
            //'id' => '',
            'type' => 'login',
            'login' => [
                'methods' => $this->authMethods,
                'auth_ids' => $authIds
            ]
        ]);
        return $apiResponse->toJsonResponse(200);
    }
}
