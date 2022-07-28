<?php namespace ec5\Libraries\Ldap;

use ErrorException;
use ec5\Libraries\Ldap\Exceptions\ConnectionException;

class LdapConnection implements ConnectionInterface
{
    /**
     * Indicates whether or not to use SSL
     *
     * @var bool
     */
    protected $ssl = false;

    /**
     * Indicates whether or not to use TLS
     * If it's used ensure that ssl is set to false and vice-versa
     *
     * @var bool
     */
    protected $tls = false;

    /**
     * The current LDAP Connection
     *
     * @var resource
     */
    protected $connection;

    /**
     * Indicates whether or not the current connection is bound
     * @var bool
     */
    protected $bound = false;

    /**
     * Port number
     *
     * @var int
     */
    protected $port;

    /**
     * Base DN
     *
     * @var int
     */
    protected $baseDn;

    /**
     * Default fields to fetch a search or read by
     *
     * @var array
     */
    protected $returnedFields = [];

    /**
     * Default filter to execute a search query on
     *
     * @var string
     */
    private $userNameAttribute;

    /**
     * Array of domain controller(s) to balance LDAP queries
     *
     * @var array
     */
    protected $domainController;

    /**
     * @var
     */
    protected $bindDn;

    /**
     * @var
     */
    protected $bindDnPassword;

    /**
     * @var
     */
    protected $errors = [];

    /**
     * @param array $config
     * @throws ConnectionException
     */
    public function __construct(array $config)
    {
        // check we were given any configuration options
        if (count($config) == 0) {
            $this->errors['ldap'] = 'ec5_56';
        } else {
            // set connection parameters from ldap config file
            if ($config['ssl']) $this->ssl = true;
            $this->port = $config['port'];
            $this->baseDn = $config['base_dn'];
            $this->domainController = $config['domain_controller'];
            $this->bindDn = $config['bind_dn'];
            $this->bindDnPassword = $config['bind_dn_password'];
            $this->userNameAttribute = $config['user_name_attribute'];

            // Connect
            $this->connect($this->domainController);

            // Set ldap version
            $this->option($this::VERSION);

            // Bind and authenticate db user to ldap server
            $this->bindDnUser($this->bindDn, $this->bindDnPassword);
        }

    }

    /**
     * Initialises a Connection via hostname
     *
     * @param string $hostname
     * @throws ConnectionException
     */
    public function connect($hostname)
    {
        $protocol = $this->ssl ? $this::PROTOCOL_SSL : $this::PROTOCOL;

        // Connect
        // Check first if ldap extension is installed
        if (function_exists('ldap_connect')) {
            try {
                $this->connection = ldap_connect($protocol . $hostname . ':' . $this->port);
            } catch (ErrorException $e) {
                $this->errors['ldap'] = 'ec5_33';
            }

        } else {
            $this->errors['ldap'] = 'ec5_57';
        }

    }

    /**
     * Binds Server DN User to LDAP server
     *
     * @param $username
     * @param $password
     *
     *
     * @throws ConnectionException
     */
    public function bindDnUser($username, $password)
    {
        // Tries to run the LDAP Connection as TLS
        if ($this->tls) {
            if (!ldap_start_tls($this->connection)) {
                $this->errors['ldap'] = 'ec5_59';
            }
        }

        try {
            $this->bound = ldap_bind($this->connection, $username, $password);
        } // If we have an exception, the admin user credentials are not valid
        catch (ErrorException $e) {
            $this->errors['ldap'] = 'ec5_58';
        }

    }

    /**
     * Binds User to LDAP server
     *
     * @param $username
     * @param $password
     *
     * @return bool
     *
     * @throws ConnectionException
     */
    public function bindUser($username, $password)
    {
        try {
            $this->bound = ldap_bind($this->connection, $username, $password);
        } // If we have an exception, the user credentials are not valid
        catch (ErrorException $e) {
            $this->bound = false;
        }

        return $this->bound;
    }

    /**
     * @param $value
     */
    public function option($value)
    {
        // Attempt to set the ldap option
        try {
            ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, $value);
        } catch (ErrorException $e) {
            $this->errors['ldap'] = 'ec5_33';
        }
    }

    /**
     * @return string
     */
    public function error()
    {
        return ldap_error($this->connection);
    }

    /**
     * Execute a search query in the entire LDAP tree
     *
     * @param string $filter
     * @return array $entry|null
     */
    public function find($filter)
    {
        // Search for a user
        $results = $this->search(
            $this->baseDn,
            $this->userNameAttribute . '=' . $filter,
            $this->returnedFields
        );


        // Results found
        if ($results) {

            // Retrieve the entry for the results
            $entry = $this->entry($results);

            // Returning a single LDAP entry
            if (isset($entry[0]) && !empty($entry[0])) {
                // Return the user name attribute and entry so that
                // We can correctly store the right field in the database
                return ['userNameAttribute' => $this->userNameAttribute, 'entry' => $entry[0]];
            }
        }

        return null;
    }

    /**
     * @param string $dn
     * @param string $filter
     * @param array $fields
     * @return resource
     * @throws ConnectionException
     */
    public function search($dn, $filter, array $fields)
    {
        try {
            return ldap_search($this->connection, $dn, $filter, $fields);
        } catch (ErrorException $e) {
            $this->errors['ldap'] = 'ec5_203';
        }

    }

    /**
     * @param $result
     * @return array
     */
    public function entry($result)
    {
        return ldap_get_entries($this->connection, $result);
    }

    /**
     * @return bool
     */
    public function bound()
    {
        return $this->bound;
    }

    /**
     * @return bool
     */
    public function ssl()
    {
        return $this->ssl;
    }

    /**
     * @return bool
     */
    public function tls()
    {
        return $this->tls;
    }

    /**
     * @return resource
     */
    public function connection()
    {
        return $this->connection;
    }

    /**
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return count($this->errors) > 0;
    }

}