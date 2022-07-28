<?php namespace ec5\Libraries\Ldap;

use Illuminate\Support\Facades\Facade;

class Ldap extends Facade
{

    protected static function getFacadeAccessor()
    {
        return 'ldap';
    }
}