<?php
/**
 * Created by PhpStorm.
 * User: mirko
 * Date: 20/06/2019
 * Time: 13:20
 */

namespace ec5\Libraries\Utilities;

use Carbon\Carbon;

class DateFormatConverter
{

    public static function mySQLToISO($mysqlDate): string
    {
        //datetime format in PHP https://www.php.net/manual/en/function.date.php
        return (new \DateTime($mysqlDate))->format('Y-m-d\TH:i:s.000') . 'Z';
    }

    //passing in created_at (request) and created_at DB
    //{"request":"2020-10-21T13:08:22.398Z","db":"2020-10-21 13:08:22"}
    public static function areTimestampsEqual($requestTimestamp, $dbTimestamp): bool
    {
        $requestDate = explode('T', $requestTimestamp ?? '')[0];
        $requestTime = explode('.', explode('T', $requestTimestamp ?? '')[1])[0];
        $dbDate = explode(' ', $dbTimestamp ?? '')[0];
        $dbTime = explode(' ', $dbTimestamp ?? '')[1];

        return $requestDate === $dbDate && $requestTime === $dbTime;
    }

    /**
     * @param $query
     * @return string[]
     *
     * get the newest and oldest dates of this subset (before pagination occurs)
     * and set the format to be like the one from JS for consistency
     * like 2020-12-10T11:31:30.000Z
     */
    public static function getNewestAndOldestFormatted($query): array
    {
        $now = Carbon::now()->toIso8601String();
        $oldest = str_replace('+00:00', '.000Z', $now);
        $newest = str_replace('+00:00', '.000Z', $now);

        if ($query->first() !== null) {
            //this will have .xxx milliseconds
            $oldestRaw = $query->min('created_at');
            $newestRaw = $query->max('created_at');
            //remove milliseconds (legacy Laravel behavior pre 7)
            $oldestParsed = Carbon::parse($oldestRaw)->format('Y-m-d H:i:s');
            $newestParsed = Carbon::parse($newestRaw)->format('Y-m-d H:i:s');

            $oldest = str_replace(' ', 'T', $oldestParsed) . '.000Z';
            $newest = str_replace(' ', 'T', $newestParsed) . '.000Z';
        }

        return [
            'oldest' => $oldest,
            'newest' => $newest
        ];
    }
}
