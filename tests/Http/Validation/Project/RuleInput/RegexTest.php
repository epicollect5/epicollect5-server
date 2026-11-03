<?php

namespace Tests\Http\Validation\Project\RuleInput;

class RegexTest extends InputTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->type = 'text';
        $this->reset();
    }

    public function test_empty_regex_is_valid()
    {
        $this->inputDetails['regex'] = '';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function test_valid_regex_is_valid()
    {
        $this->inputDetails['regex'] = '[0-9]+';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function test_regex_containing_slash_is_valid()
    {
        $this->inputDetails['regex'] = 'a/b';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function test_malformed_regex_returns_ec5_392()
    {
        $this->inputDetails['regex'] = '[0-9';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals(['ec5_392'], $this->validator->errors()[$this->inputDetails['ref']]);
        $this->validator->resetErrors();
    }

    public function test_production_malformed_regexes_return_ec5_392()
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
            $this->inputDetails['regex'] = $pattern;
            $this->validator->validate($this->inputDetails);
            $this->validator->additionalChecks($this->parentRef);
            $this->assertTrue($this->validator->hasErrors(), "Expected errors: $pattern");
            $this->assertEquals(['ec5_392'], $this->validator->errors()[$this->inputDetails['ref']], "Expected ec5_392: $pattern");
            $this->validator->resetErrors();
        }
    }
}
