<?php

namespace ec5\Libraries\Jwt;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Guard;
use ec5\Libraries\Jwt\JwtUserProvider as UserProvider;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use RuntimeException;
use Cookie;
use Route;

class JwtGuard implements Guard
{
    /**
     * The currently authenticated user.
     */
    protected AuthenticatableContract|null $user = null;

    /**
     * The user we last attempted to retrieve.
     */
    protected AuthenticatableContract $lastAttempted;

    /**
     * The user provider implementation.
     *
     * @var \Illuminate\Contracts\Auth\UserProvider
     */
    protected $provider;

    /**
     * The Illuminate cookie creator service.
     *
     * @var \Illuminate\Contracts\Cookie\QueueingFactory
     */
    protected $cookie;

    /**
     * The request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * Indicates if the logout method has been called.
     *
     * @var bool
     */
    protected $loggedOut = false;

    /**
     * The JWT class
     *
     * @var
     */
    protected $jwt;

    /**
     * The JWT token
     */
    protected $jwtToken;

    /**
     * The name of the field on the request containing the API token.
     */
    protected string $inputKey;

    /**
     * The name of the token "column" in persistent storage.
     */
    protected string $storageKey;

    /**
     * External request url that should be checked for
     * a JWT token differently to an internal url
     */
    protected string $externalRequestUrl = '/api';

    protected $session;

    /**
     * JwtGuard constructor.
     * @param JwtUserProvider $provider
     * @param Request|null $request
     * @param Jwt $jwt
     */
    public function __construct(UserProvider $provider, Request $request = null, Jwt $jwt)
    {
        $this->provider = $provider;
        $this->request = $request;
        $this->jwt = $jwt;
        $this->inputKey = 'jwt';
        $this->storageKey = 'api_token';
    }

    /**
     * Determine if the current user is authenticated.
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the current user is a guest.
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the currently authenticated user.
     *
     * @return AuthenticatableContract|null
     */
    public function user()
    {
        if ($this->loggedOut) {
            return null;
        }

        // If we've already retrieved the user for the current request we can just
        // return it back immediately. We do not want to fetch the user data on
        // every call to this method because that would be tremendously slow.
        if (!is_null($this->user)) {
            return $this->user;
        }

        // Retrieve the jwt token for the request
        $this->jwtToken = $this->getTokenForRequest();

        if ($this->jwtToken) {

            // Retrieve api_token value
            $subject = $this->jwt->getSubject($this->jwtToken);

            // Check api_token is valid
            if ($subject) {

                // todo expired jwt?

                // Retrieve the user
                $this->user = $this->provider->retrieveByCredentials(
                    [$this->storageKey => $subject]
                );
                if (!$this->user) {
                    return null;
                }
            }
        }

        return $this->user;
    }

    public function jwtToken()
    {
        return $this->jwtToken;
    }

    /**
     * Get the token for the current request (if any)
     * Try to retrieve from request input, cookie or auth bearer
     */
    protected function getTokenForRequest(): string|null
    {
        // Check if external or internal api request
        if (preg_match('#^' . $this->externalRequestUrl . '#', $this->request->getPathInfo())) {
            // If external, check for jwt in input or authorization bearer header
            $token = $this->request->input($this->inputKey);

            if (empty($token)) {
                $token = $this->request->bearerToken();
            }
        } else {
            // If internal, check for jwt in cookie only
            $token = $this->request->cookie($this->inputKey);
        }
        return $token;
    }

    /**
     * Get the jwt header in the Authorization header format
     */
    public function authorizationResponse(): array
    {
        return [
            'type' => 'jwt',
            'jwt' => $this->jwtToken()
        ];
    }

    /**
     * Get the ID for the currently authenticated user.
     */
    public function id()
    {
        //
    }

    /**
     * Validate a user's credentials.
     */
    public function validate(array $credentials = []): bool
    {
        return $this->attempt($credentials, false, false);
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     */
    public function attempt(array $credentials = [], $remember = false, $login = true): bool
    {
        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        // If an implementation of UserInterface was returned, we'll ask the provider
        // to validate the user against the given credentials, and if they are in
        // fact valid we'll log the users into the application and return true.
        if ($this->hasValidCredentials($user, $credentials)) {
            if ($login) {
                $this->login($user, false, false);
            }
            return true;
        }
        return false;
    }

    /**
     * Determine if the user matches the credentials.
     */
    protected function hasValidCredentials($user, $credentials): bool
    {
        return !is_null($user) && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Log a user into the application.
     *
     * @param AuthenticatableContract $user
     * @param bool $remember
     * @param bool $setCookie
     * @return void
     */
    public function login(AuthenticatableContract $user, $remember = false, $setCookie = false): void
    {
        /**
         * imp: MASSIVE HACK: passwordless auth route gets a shorter expire jwt
         *
         * This is mainly to avoid building a custom guard just
         * to have a different jwt expiry time for the passwordless auth on mobile devices
         */
        $isPasswordless = Route::currentRouteName() === 'passwordless-auth';

        $jwtConfig = config('auth.jwt');

        // Generate new api_token
        $token = $this->jwt->generateApiToken($user->id);

        // Save api token to user
        $this->saveToken($user, $token);

        if ($isPasswordless) {
            /**
             * we need to send a jwt token to the user
             * bypassing the jwt driver because we want a token that expires in 24 hours
             * or so, while keeping jwt guard used by Google Auth with a longer expiry time
             *
             * therefore we generate the jwt here when the passwordless auth is requested
             *
             */
            $this->jwtToken = $this->jwt->generatePasswordlessToken($token);
        } else {
            $this->jwtToken = $this->jwt->generateToken($token);
        }

        if ($setCookie) {
            // Add jwt cookie to queue
            // Set expiry time to reflect the jwt expire time
            $this->cookie->queue(cookie($this->inputKey, $this->jwtToken, ($jwtConfig['expire'] / 60)));
        }

        $this->setUser($user);
    }

    /**
     * Log the user out of the application.
     *
     * @return void
     */
    public function logout()
    {
        // Remove the api token from user
        if ($user = $this->user()) {
            $this->saveToken($user, '');
        }

        // Clear cookie data
        $this->clearUserDataFromStorage();

        // Null user
        $this->user = null;
        $this->loggedOut = true;
    }

    /**
     * Get the user provider used by the guard.
     */
    public function getProvider(): \Illuminate\Contracts\Auth\UserProvider|JwtUserProvider
    {
        return $this->provider;
    }

    public function setProvider($provider): void
    {
        $this->provider = $provider;
    }

    /**
     * Return the currently cached user.
     *
     * @return AuthenticatableContract|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the current user.
     *
     * @param AuthenticatableContract $user
     * @return void
     */
    public function setUser(AuthenticatableContract $user)
    {
        $this->user = $user;

        $this->loggedOut = false;
    }

    /**
     * Get the current request instance.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request ?: Request::createFromGlobals();
    }

    /**
     * Set the current request instance.
     *
     * @param Request $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get the last user we attempted to authenticate.
     *
     * @return AuthenticatableContract
     */
    public function getLastAttempted()
    {
        return $this->lastAttempted;
    }

    /**
     * @param AuthenticatableContract $user
     * @param $token
     */
    public function saveToken(AuthenticatableContract $user, $token)
    {
        $storageKey = $this->storageKey;
        $user->$storageKey = $token;
        // Save token
        $user->save();
    }

    /**
     * Remove the user data from the cookies.
     *
     * @return void
     */
    protected function clearUserDataFromStorage()
    {
        $this->cookie->queue(Cookie::forget($this->inputKey));
    }

    /**
     * Get the cookie creator instance used by the guard.
     *
     * @return CookieJar
     * @throws RuntimeException
     */
    public function getCookieJar()
    {
        if (!isset($this->cookie)) {
            throw new RuntimeException('Cookie jar has not been set.');
        }

        return $this->cookie;
    }

    /**
     * Set the cookie creator instance used by the guard.
     *
     * @param \Illuminate\Contracts\Cookie\QueueingFactory $cookie
     * @return void
     */
    public function setCookieJar(CookieJar $cookie)
    {
        $this->cookie = $cookie;
    }


    public function hasUser(): bool
    {
        // Return true if a user is authenticated
        return !is_null($this->user);
    }
}
