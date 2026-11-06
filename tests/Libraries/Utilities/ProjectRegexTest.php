<?php

namespace Tests\Libraries\Utilities;

use ec5\Libraries\Utilities\ProjectRegex;
use PHPUnit\Framework\TestCase;

class ProjectRegexTest extends TestCase
{
    public function test_empty_pattern_is_valid(): void
    {
        $this->assertTrue(ProjectRegex::isValid(''));
        $this->assertNull(ProjectRegex::error(''));
    }

    public function test_non_string_pattern_is_valid(): void
    {
        $this->assertTrue(ProjectRegex::isValid(null));
        $this->assertTrue(ProjectRegex::isValid(123));
    }

    public function test_compile_picks_delimiter_free_of_pattern(): void
    {
        $this->assertSame('#(*UTF)a/b#', ProjectRegex::compile('a/b'));
        $this->assertSame('/(*UTF)a#b/', ProjectRegex::compile('a#b'));
        $this->assertSame('regex:#(*UTF)a/b#', ProjectRegex::toRule('a/b'));
    }

    public function test_compile_escapes_delimiter_when_all_present(): void
    {
        $pattern = '[a/#~%!;:,=@|^+*\\-\'`?&\d]+';
        $compiled = ProjectRegex::compile($pattern);
        $this->assertSame('/(*UTF)' . str_replace('/', '\\/', $pattern) . '/', $compiled);
        $this->assertNotSame('cannot validate: pattern contains every candidate delimiter', ProjectRegex::error($pattern));
        $this->assertNull(ProjectRegex::error($pattern));
    }

    public function test_compiled_patterns_use_utf_without_ucp(): void
    {
        // Shorthand classes stay ASCII, matching the mobile app (pcre2-wasm FLAGS.UTF)
        // and the legacy byte-mode gate.
        $this->assertSame(1, preg_match(ProjectRegex::compile('^\d+$'), '12'));
        $this->assertSame(0, preg_match(ProjectRegex::compile('^\d+$'), '１２'));
        $this->assertSame(1, preg_match(ProjectRegex::compile('^\w+$'), 'abc'));
        $this->assertSame(0, preg_match(ProjectRegex::compile('^\w+$'), 'é'));
        $this->assertSame(0, preg_match(ProjectRegex::compile('^\s$'), "\u{00A0}"));

        // UTF-8 codepoint semantics for . and quantifiers are preserved.
        $this->assertSame(1, preg_match(ProjectRegex::compile('^.{2}$'), 'éé'));

        // A project pattern that opts into UCP still gets it.
        $this->assertSame(1, preg_match(ProjectRegex::compile('(*UCP)^\d+$'), '１２'));
    }

    public function test_compiled_rule_matches_same_as_builtin(): void
    {
        $cases = [
            'a/b' => ['a/b', 'axb'],
            '\d+/\d+' => ['12/34', 'nomatchhere'],
        ];
        foreach ($cases as $pattern => [$match, $noMatch]) {
            $compiled = ProjectRegex::compile($pattern);
            $this->assertSame(1, preg_match($compiled, $match));
            $this->assertSame(0, preg_match($compiled, $noMatch));
        }
    }

    public function test_valid_patterns(): void
    {
        $this->assertTrue(ProjectRegex::isValid('[0-9]+'));
        $this->assertTrue(ProjectRegex::isValid('^[A-Z]{2,4}$'));
    }

    public function test_valid_pattern_containing_slash(): void
    {
        $this->assertTrue(ProjectRegex::isValid('a/b'));
        $this->assertTrue(ProjectRegex::isValid('\d+/\d+'));
    }

    public function test_malformed_patterns(): void
    {
        $this->assertFalse(ProjectRegex::isValid('[0-9'));
        $this->assertFalse(ProjectRegex::isValid('(abc'));
        $this->assertNotNull(ProjectRegex::error('[0-9'));
    }

    public function test_trailing_backslash_is_invalid(): void
    {
        $this->assertFalse(ProjectRegex::isValid('abc\\'));
        $this->assertFalse(ProjectRegex::isValid('a/b\\'));
        $this->assertNotNull(ProjectRegex::error('abc\\'));
    }

    public function test_legacy_malformed_patterns_are_invalid(): void
    {
        $patterns = [
            '^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/(19|20',
            '^[16-121]+$',
            '+()',
            '^.{1,00}$',
            '^{8,9}$',
            '^(3[0-6][0]|3[0-5][0-9]|[12]?[0-9]{1,2})$ (Only al',
            '^\C\M\d\d\d\d$',
            '^.{10,00}$',
            '(10,10}$',
            '{10,11}',
            '****-***',
            '{10,10}',
            '[a-z0-9!#$%&\'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+',
            '*.*',
            '^[A-Z]*$,^{1,4}$',
            '{2,20}$',
            '{10,10}$',
            '^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$',
            '^[0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$',
            '^[18-60]+$',
            '^.{2,20)$',
            '^[a-zA-ZO-9._%+]+@[a-zA-ZO-9.-]+\.[a-zA-Z]{2,}$',
            '⁽1,6)$',
            '^[18-65]+$',
            '^{10}',
            '^{8,8}$',
            '^.{1,10)$',
            '^[9-7]+$',
            '^[25-110]+$',
            '.^{10,10}$',
            '^.{1,9000000000000000000000000000000000000000000}$',
            '^.{1,9000000000000000000000000000000000000}$',
            '^.{1,3)$',
            '^.{1,1000000}$',
            '[below 18] + [between18- 60] + [above 60]',
            '^[10-10+$',
            '{13}$',
            '^{1,2}$',
        ];

        foreach ($patterns as $pattern) {
            $this->assertFalse(ProjectRegex::isValid($pattern), "Expected invalid: $pattern");
            $this->assertNotNull(ProjectRegex::error($pattern), "Expected error: $pattern");
        }
    }
}
