<?php

namespace ec5\Models\Projects\Exceptions;

use Exception;
use Config;

/*
|--------------------------------------------------------------------------
| ProjectNameMissingException
|--------------------------------------------------------------------------
|
*/

class ProjectNameMissingException extends Exception
{
    /**
     * ProjectDataMissingException constructor.
     */
    public function __construct()
    {
        parent::__construct(Config::get('status_codes.ec5_224'));
    }
}
