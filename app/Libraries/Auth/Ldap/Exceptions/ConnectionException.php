<?php

namespace ec5\Libraries\Auth\Ldap\Exceptions;

use Exception;

class ConnectionException extends Exception
{
    /**
     * ConnectionException constructor.
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }

}
