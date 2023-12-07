<?php
/**
 * Created by PhpStorm.
 * User: mirko
 * Date: 20/06/2019
 * Time: 13:20
 */

namespace ec5\Libraries\Utilities;

class DateFormatConverter
{

    public static function mySQLToISO($mysqlDate)
    {
        //datetime format in PHP https://www.php.net/manual/en/function.date.php
        return (new \DateTime($mysqlDate))->format('Y-m-d\TH:i:s.000') . 'Z';
    }

    //passing in created_at (request) and created_at DB
    //{"request":"2020-10-21T13:08:22.398Z","db":"2020-10-21 13:08:22"}
    public static function areTimestampsEqual($requestTimestamp, $dbTimestamp): bool
    {
        $requestDate = explode('T', $requestTimestamp)[0];
        $requestTime = explode('.', explode('T', $requestTimestamp)[1])[0];
        $dbDate = explode(' ', $dbTimestamp)[0];
        $dbTime = explode(' ', $dbTimestamp)[1];

        return $requestDate === $dbDate && $requestTime === $dbTime;
    }
}
