<?php

/**
 * Created by PhpStorm.
 * User: mirko
 * Date: 04/08/2020
 * Time: 11:11
 */

namespace ec5\Libraries\Utilities;

use Illuminate\Support\Str;

class Common
{
    public static function getPossibleAnswers($input): array
    {
        $possibleAnswers = $input['possible_answers'];
        $possibles = [];

        if (!empty($possibleAnswers)) {
            foreach ($possibleAnswers as $possibleAnswer) {
                $possibles[] = $possibleAnswer['answer_ref'];
            }
        }

        return $possibles;
    }

    public static function generateFilename($prefix, $f): string
    {
        /**
         *  Truncate anything bigger than 100 chars
         *  to keep the filename unique, a prefix is passed
         *
         *  We do this as we might have filename too long (i.e branch question of 255,
         *  then adding prefix we go over the max filename length (255)
         */

        return $prefix . '__' . Str::slug(substr(strtolower($f), 0, 100));
    }

    public static function isValidTimestamp($timestamp): bool
    {
        return ((string)(int)$timestamp === $timestamp)
            && ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }

    public static function formatBytes($bytes, $precision = 2): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        // $bytes /= pow(1024, $pow);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function roundNumber($number, $round = 2)
    {
        $unit = ['', 'K', 'M', 'B', 'T'];

        if ($number === 0) {
            return 0;
        }

        if ($number < 10) {
            return $number;
        }

        if ($number < 1000) {
            return '≈ ' . ceil($number / 10) * 10;
        }

        return round($number / pow(1000, ($i = floor(log($number, 1000)))), $round) . $unit[$i];
    }


}
