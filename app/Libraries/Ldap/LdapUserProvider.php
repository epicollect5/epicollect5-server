<?php namespace ec5\Libraries\Ldap;

class LdapUserProvider
{
    /**
     * Active LDAP Connection
     *
     * @var object
     */
    protected $connection;

    /**
     * @var
     */
    protected $errors;

    /**
     * @param $connection
     */
    public function __construct(LdapConnection $connection)
    {
        $this->connection = $connection;
        // check if the connection had any errors
        $this->errors = $this->connection->errors();
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     * @return LdapUser
     */
    public function retrieveByCredentials(array $credentials)
    {
        $userName = $credentials['username'];
        $result = $this->connection->find($userName);

        if(!empty($result)){

            $user = new LdapUser;
            $user->setEntry($result);
            // check if this user is authenticated via DN and password
            if ($this->auth($user->getDn(), $credentials['password'])) {
                return $user;
            }

        }

        return null;
    }


    /**
     * Rebinds with a given DN and Password
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     *
     * @throws Exceptions\ConnectionException
     */
    public function auth($username, $password)
    {
        return $this->connection->bindUser($username, $password);
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return count($this->errors) > 0;
    }

    /**
     * @return mixed
     */
    public function errors()
    {
        return $this->errors;
    }

}