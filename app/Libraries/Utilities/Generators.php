<?php

namespace ec5\Libraries\Utilities;

use Faker;

class Generators
{
    /*
     * Generate a random number passing how many digits
     * and how many duplicated digits
     */
    public static function randomNumber($len = 6, $dup = 1)
    {
        if ($dup < 1) {
            $dup = 1;
        }

        $num = range(0, 9);
        shuffle($num);

        $num = array_slice($num, 0, ($len - $dup) + 1);

        if ($dup > 0) {
            $k = array_rand($num, 1);
            for ($i = 0; $i < ($dup - 1); $i++) {
                $num[] = $num[$k];
            }
        }
        return implode('', $num);
    }
    public static function input($formRef)
    {

        $faker = Faker\Factory::create();
        //to remove the dot https://stackoverflow.com/questions/72245440/how-with-faker-to-get-text-without-ending
        $question = substr(str_replace('.', '', $faker->unique()->sentence(5)), 0, 25);

        return [
            'max' => null,
            'min' => null,
            'ref' => $formRef . '_' . uniqid(),
            'type' => 'text',
            'group' => [],
            'jumps' => [],
            'regex' => null,
            'branch' => [],
            'verify' => false,
            'default' => null,
            'is_title' => false,
            'question' => $question,
            'uniqueness' => 'none',
            'is_required' => false,
            'datetime_format' => null,
            'possible_answers' => [],
            'set_to_current_datetime' => false
        ];
    }
}
