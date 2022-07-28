<?php

namespace ec5\Libraries\Utilities;

class Generators
{
    /*
     * Generate a random number passing how many digits
     * and how many duplicated digits
     */
    public static function randomNumber($len = 6, $dup = 1) {
        if ($dup < 1) {
            $dup = 1;
        }

        $num = range(0,9);
        shuffle($num);

        $num = array_slice($num, 0, ($len-$dup)+1);

        if ($dup > 0) {
            $k = array_rand($num, 1);
            for ($i=0; $i<($dup-1); $i++) {
                $num[] = $num[$k];
            }
        }
        return implode('', $num);
    }
}
