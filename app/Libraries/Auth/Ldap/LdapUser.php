<?php

namespace ec5\Libraries\Auth\Ldap;

use Illuminate\Contracts\Auth\Authenticatable as Authenticatable;

class LdapUser implements Authenticatable
{
    /**
     * @var string $name
     */
    public $name;

    /**
     * @var string $lastName
     */
    protected $lastName;

    /**
     * @var string $username
     */
    protected $username;

    /**
     * @var string $email
     */
    public $email;

    /**
     * @var string $open_id
     */
    protected $open_id = '';


    /**
     * @var string $dn
     */
    protected $dn;

    /**
     * @var array $member_of
     */
    protected $member_of = [];

    /**
     * @var array $schools
     */
    protected $schools = [];

    /**
     * The user name attribute
     *
     * @var
     */
    protected $userNameAttribute;

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->email;
    }

    /**
     * Get the username for the user.
     *
     * @return mixed
     */
    public function getUserName()
    {
        return $this->username;
    }

    /**
     * Get the name for the user.
     *
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the name for the user.
     *
     * @return mixed
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        // This isn't be needed as you cannot directly access the password
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @return string
     */
    public function getRememberToken()
    {
        // This isn't be needed as user / password is in ldap
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param string $value
     * @return void
     */
    public function setRememberToken($value)
    {
        // This isn't be needed as user / password is in ldap
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        // This isn't be needed as user / password is in ldap
    }

    /**
     * Setting the current User
     *
     * @param array $details
     */
    public function setEntry(array $details)
    {
        // Set the user name attribute
        $this->userNameAttribute = $details['userNameAttribute'];

        // Retrieve the relevant email attribute value
        $this->email = $details['entry']['mail'][0];

        // Retrieve the relevant user name attribute value
        $this->username = $details['entry'][$this->userNameAttribute][0];

        // Set other fields
        $this->name = (!empty($details['entry']['givenname'][0]) ? $details['entry']['givenname'][0] : '');
        $this->lastName = (!empty($details['entry']['sn'][0]) ? $details['entry']['sn'][0] : '');
        $this->dn = $details['entry']['dn'];

    }

    /**
     * Return distinguished name of the User
     *
     * @return string
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * @return array
     */
    public function getGroups()
    {
        return $this->member_of;
    }

    /**
     * @param string $group
     * @return bool
     */
    public function isMemberOf($group)
    {
        foreach ($this->member_of as $groups) {
            if (preg_match('/^CN=' . $group . '/', $groups)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthPasswordName()
    {
        // TODO: Implement getAuthPasswordName() method.
    }
}
