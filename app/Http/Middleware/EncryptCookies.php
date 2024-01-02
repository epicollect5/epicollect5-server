<?php

namespace ec5\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncrypter;

class EncryptCookies extends BaseEncrypter
{
    /**
     * The names of the cookies that should not be encrypted.
     */
    protected $except = [
        'epicollect5-download-entries'//this cookie is set when downloading the data as a csv/json file
    ];
}
