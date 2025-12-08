<?php

namespace Tests\Libraries\Utilities;

use ec5\Libraries\Utilities\Arrays;
use PHPUnit\Framework\TestCase;

class ArraysTest extends TestCase
{
    /**
     * Test Arrays::merge() function
     */
    public function testMerge()
    {
        // Merging from 2nd array into 1st
        $this->assertEquals(['a' => ['b' => 'c']], Arrays::merge(['a' => ['b' => '']], ['a' => ['b' => 'c']]));

        // Merging from 2nd array into 1st, with extra keys in 1st
        $this->assertEquals(['a' => ['b' => 'c', 'd' => 'e']],
            Arrays::merge(['a' => ['b' => '', 'd' => 'e']], ['a' => ['b' => 'c']]));
    }

    /**
     * Test Arrays::implodeIt() function
     */
    public function testImplodeIt()
    {
        // Implode to retrieve all VALUES
        $this->assertEquals('c,e', Arrays::implodeMulti(['a' => ['b' => 'c', 'd' => 'e']], ','));
        $this->assertEquals('cea1🌐1.1@',
            Arrays::implodeMulti([
                'a' => [
                    // letter
                    'b' => 'c',
                    // letter
                    'd' => 'e',
                    // empty array
                    'f' => [],
                    // array
                    'g' => [1 => 'a'],
                    // object
                    'h' => new \stdClass(),
                    // int
                    'i' => 1,
                    // emoji
                    'j' => '🌐',
                    // decimal
                    'k' => 1.1,
                    // empty string
                    'l' => '',
                    // symbol
                    'm' => '@'
                ]
            ]));
    }
}
