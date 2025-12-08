<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Controllers\Controller;
use ec5\Libraries\Auth\Jwt\JwtUserProvider;
use Response;

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

    protected JwtUserProvider $provider;
    protected array $authMethods;
    protected string $appleProviderLabel;
    protected string $googleProviderLabel;
    protected string $localProviderLabel;
    protected string $ldapProviderlabel;
    protected string $passwordlessProviderLabel;
    protected bool $isAuthApiLocalEnabled;

    public function __construct(JwtUserProvider $provider)
    {
        $this->provider = $provider;
        //set providers values
        $this->appleProviderLabel = config('epicollect.strings.providers.apple');
        $this->googleProviderLabel = config('epicollect.strings.providers.google');
        $this->localProviderLabel = config('epicollect.strings.providers.local');
        $this->ldapProviderlabel = config('epicollect.strings.providers.ldap');
        $this->passwordlessProviderLabel = config('epicollect.strings.providers.passwordless');

        // Determine which authentication methods are available
        $this->authMethods = config('auth.auth_methods');
        $this->isAuthApiLocalEnabled = config('auth.auth_api_local_enabled');
    }

    public function getLogin()
    {
        $authIds = [];

        // If Google is an auth method, supply our Client ID
        if (in_array('google', $this->authMethods)) {
            $providerKey = config('services.google_api');
            $authIds['google']['CLIENT_ID'] = $providerKey['client_id'];
            $authIds['google']['SCOPE'] = $providerKey['scope'];
        }

        $data = [
            'type' => 'login',
            'login' => [
                'methods' => $this->authMethods,
                'auth_ids' => $authIds
            ]
        ];

        return Response::apiData($data);
    }
}
