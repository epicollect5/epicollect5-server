<?php namespace ec5\Libraries\Ldap\Exceptions;

use Exception;

class MissingConfigurationException extends Exception
{
    /**
     * MissingConfigurationException constructor.
     */
    public function __construct()
    {
        parent::__construct("Please ensure that a ldap.php file is present in ROOT/config/");
    }
}