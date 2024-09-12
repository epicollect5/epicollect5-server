<?php
/**
 * Created by PhpStorm.
 * User: mirko
 * Date: 25/03/2020
 * Time: 14:53
 */

namespace ec5\Exceptions;

use Exception;

class UserNotVerifiedException extends Exception
{
    public function __construct()
    {
        parent::__construct(config('epicollect.codes.ec5_374'));
    }
}
