<?php

namespace ec5\Libraries\Auth\Jwt;

use ec5\Models\User\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Str;

class JwtUserProvider implements UserProvider
{
    /**
     * The hasher implementation.
     */
    protected HasherContract $hasher;

    /**
     * The Eloquent user model.
     */
    protected User $model;

    /**
     * Create a new database user provider.
     */
    public function __construct(HasherContract $hasher, User $model)
    {
        $this->model = $model;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier)
    {
        return $this->createModel()->newQuery()->find($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token)
    {
        $model = $this->createModel();

        return $model->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->where($model->getRememberTokenName(), $token)
            ->first();
    }

    /**
     * Update the "remember me" token for the given user in storage.
     */
    public function updateRememberToken(UserContract $user, $token): void
    {
        $user->setRememberToken($token);
        $user->save();
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials)
    {
        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return an
        // Eloquent User "model" that will be utilized by the Guard instances.
        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value) {
            if (!Str::contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(UserContract $user, array $credentials): bool
    {
        $plain = $credentials['password'];

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

    /**
     * Create a new instance of the model.
     */
    public function createModel(): User
    {
        return $this->model;
    }

    /**
     * Gets the name of the Eloquent user model.
     */
    public function getModel(): User
    {
        return $this->model;
    }

    /**
     * Sets the name of the Eloquent user model.
     */
    public function setModel($model): static
    {
        $this->model = $model;
        return $this;
    }

    public function findUserByEmail($email)
    {
        return $this->retrieveByCredentials(['email' => $email]);
    }

    //imp: this method is here just to make it compile, never used
    //copied from Illuminate\Auth\EloquentUserProvider
    public function rehashPasswordIfRequired(UserContract $user, array $credentials, bool $force = false): void
    {
        //todo:
    }
}
