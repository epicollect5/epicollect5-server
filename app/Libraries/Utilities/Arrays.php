<?php

namespace ec5\Libraries\Utilities;

class Arrays
{
    /**
     * Takes values from $array2 which have the same key as $array1 and stores them in $to array
     * If a key is not present in $array2, but present in $array1, will take the value from $array1
     *
     * Works recursively for multidimensional associative arrays
     */
    public static function merge(array $array1, array $array2): array
    {
        $to = [];
        // Loop $array1
        foreach ($array1 as $key => $value) {
            // If the key exists in $array2
            if (isset($array2[$key])) {
                // If we have an array
                if (is_array($value)) {
                    // If the array is empty, set the data
                    if (count($value) == 0) {
                        $to[$key] = $array2[$key];
                    } else {
                        // Otherwise, call this function again with the array
                        $to[$key] = self::merge($value, $array2[$key]);
                    }
                } else {
                    // Otherwise, set the value for this key from $array2
                    $to[$key] = $array2[$key];
                }
            } else {
                // Otherwise set the value from $array1
                $to[$key] = $value;
            }
        }
        return $to;
    }

    /**
     * Implode an array to receive all values in the array
     *
     * Works recursively for multidimensional associative arrays
     */
    public static function implodeMulti(array $pieces, string $glue = '', array $to = []): string
    {
        foreach ($pieces as $key => $value) {
            if (is_array($value)) {
                // Recursively implode arrays
                $to[] = self::implodeMulti($value, $glue);
            } elseif (is_scalar($value)) {
                // Append scalar values (string, int, float, bool) to the array
                $to[] = $value;
            } else {
                // Skip objects and other non-scalar values
                continue;
            }
        }

        // Implode the array into a string using the specified glue
        return implode($glue, $to);
    }
}
