<?php

namespace Libraries\Utilities;

use ec5\Libraries\Utilities\Strings;
use PHPUnit\Framework\TestCase;

class StringsTest extends TestCase
{
    /**
     * Testing the Strings::containsEmoji() function
     */
    public function testContainsEmoji()
    {
        $this->assertTrue(Strings::containsEmoji('☻'));
        $this->assertTrue(Strings::containsEmoji('☔'));
        $this->assertTrue(Strings::containsEmoji('⚉'));

        $this->assertFalse(Strings::containsEmoji('a'));
        $this->assertFalse(Strings::containsEmoji('ß'));
        $this->assertFalse(Strings::containsEmoji('漢'));
        $this->assertFalse(Strings::containsEmoji('1'));
        $this->assertFalse(Strings::containsEmoji('@'));
        $this->assertFalse(Strings::containsEmoji('æ'));
        $this->assertFalse(Strings::containsEmoji('ຈ'));
        $this->assertFalse(Strings::containsEmoji('ᄤ'));
        $this->assertFalse(Strings::containsEmoji('ከ'));
    }

    /**
     * Testing the Strings::containsHtml() function
     */
    public function testContainsHtml()
    {
        $this->assertTrue(Strings::containsHtml('<p><p/>'));

        $this->assertFalse(Strings::containsHtml('a'));
        $this->assertFalse(Strings::containsHtml('1'));
    }

    public function testValidUUIDFormat()
    {
        //must be a string
        $this->assertFalse(Strings::isValidUuid(0));

        //old uuid, flawed function
        $this->assertTrue(Strings::isValidUuid('50c7c75b-9ae4-10bc-101e-9e235f719ff6'));
        $this->assertTrue(Strings::isValidUuid('ceec9958-1e1f-88a5-7ab6-e4fd12219570'));
        $this->assertTrue(Strings::isValidUuid('53579fef-0e5f-0d6f-3cb1-1a8d6b9c8bbb'));

        $this->assertFalse(Strings::isValidUuid('5357&fef-0e5f-0d6f-3cb1-1a8d6b9c8bbb'));
        $this->assertFalse(Strings::isValidUuid('5357&fef-0e5f00d6f-3cb1-1a8d6b9c8bbb'));
        $this->assertFalse(Strings::isValidUuid('5357&fe--0e5f00d6f-3cb1-1a8d6b9c8bbb'));
        $this->assertFalse(Strings::isValidUuid('+357&fef-0e5f00d6f-3cb1-1a8d6b9c8bbb'));

        $this->assertFalse(Strings::isValidUuid('Aceec9958-1e1f-88a5-7ab6-e4fd12219570'));
        $this->assertFalse(Strings::isValidUuid('ceec995--1e1f-88a5-7ab6-e4fd12219570'));
        $this->assertFalse(Strings::isValidUuid('ceec9958-1e1f-88a5-7ab66-e4fd1219570'));
        $this->assertFalse(Strings::isValidUuid('53579fef-0e5f-0d6f-3cb1-1a8d6b9c8bb-'));

        //new uuid, improved (version 4)
        $this->assertTrue(Strings::isValidUuid('e983af2f-be8c-470a-92db-22087e8be26b'));
        $this->assertTrue(Strings::isValidUuid('116e8468-cf05-483b-90f7-b5ef50ac49cd'));
        $this->assertFalse(Strings::isValidUuid('116e84680-f05-483b-90f7-b5ef50ac49cd'));
        $this->assertFalse(Strings::isValidUuid('+16e84680-f05-483b-90f7-b5ef50ac49cd'));
        $this->assertFalse(Strings::isValidUuid('116e84680-f05-=83b-90f7-b5ef50ac49cd'));
    }
}
