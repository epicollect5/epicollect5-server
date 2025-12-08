<?php

namespace ec5\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncrypter;

class EncryptCookies extends BaseEncrypter
{

    /**
     * Indicates if cookies should be serialized.
     *
     * see https://laravel.com/docs/5.5/upgrade#upgrade-5.5.42
     *
     * @var bool
     */

    protected static $serialize = true;

    /**
     * The names of the cookies that should not be encrypted.
     */
    protected $except = [
        'epicollect5-download-entries'//this cookie is set when downloading the data as a csv/json file
    ];
}
