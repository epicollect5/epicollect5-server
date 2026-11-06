<?php

namespace Tests\Http\Validation\Project\RuleInput;

use ec5\Http\Validation\Entries\Upload\InputRules\RuleTextInput;
use PHPUnit\Framework\Attributes\DataProvider;

class RegexTest extends InputTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->type = 'text';
        $this->reset();
    }

    public function test_empty_regex_is_valid(): void
    {
        $this->inputDetails['regex'] = '';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function test_null_regex_is_valid(): void
    {
        $this->inputDetails['regex'] = null;
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function test_non_string_regex_is_rejected(): void
    {
        $invalidRegexes = [
            'array' => ['[0-9]+'],
            'integer' => 123,
            'boolean' => true,
        ];

        foreach ($invalidRegexes as $label => $invalidRegex) {
            $this->inputDetails['regex'] = $invalidRegex;
            $this->validator->validate($this->inputDetails);
            $this->assertTrue(
                $this->validator->hasErrors(),
                "Expected $label regex to be rejected"
            );
            $this->validator->resetErrors();
        }
    }

    public function test_valid_regex_is_valid(): void
    {
        $this->inputDetails['regex'] = '[0-9]+';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function test_regex_containing_slash_is_valid(): void
    {
        $this->inputDetails['regex'] = 'a/b';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    /**
     * @throws \Throwable
     */
    public function test_regex_containing_slash_validates_answers_on_upload(): void
    {
        $rule = new RuleTextInput();
        $project = \Mockery::mock('\ec5\DTO\ProjectDTO');
        $inputDetails = ['ref' => 'xxx', 'type' => 'text', 'regex' => 'a/b'];
        $rule->setRules($inputDetails, 'a/b', $project);
        $this->assertTrue($rule->validate(['xxx' => 'a/b']));
        $rule->resetErrors();
        $rule->setRules($inputDetails, 'axb', $project);
        $this->assertFalse($rule->validate(['xxx' => 'axb']));
        \Mockery::close();
    }

    public static function uploadRegexAnswersProvider(): array
    {
        return [
            'anchored digits' => ['^[0-9]+$', '12', 'ab'],
            'fixed length letters' => ['^[A-Z]{2,4}$', 'AB', 'ABCDE'],
            'digits around slash' => ['^\d+/\d+$', '12/34', 'nomatchhere'],
            'alternation month' => ['^(0[1-9]|1[0-2])$', '07', '13'],
            'multibyte characters count as one' => ['^.{2}$', 'éé', 'abc'],
            'unicode letter class' => ['^[A-Za-zÁÉÍÓÚáéíóúñÑ]+$', 'José', 'José1'],
            'escaped metacharacter' => ['^a\+b$', 'a+b', 'aab'],
            'several delimiters in pattern' => ['^a/b#c$', 'a/b#c', 'a/b#d'],
            'digit class stays ASCII' => ['^\d+$', '12', '１２'],
            'word class stays ASCII' => ['^\w+$', 'abc', 'é'],
        ];
    }

    #[DataProvider('uploadRegexAnswersProvider')]
    public function test_regex_accepts_matching_and_rejects_non_matching_answers_on_upload(
        string $pattern,
        string $matchingAnswer,
        string $nonMatchingAnswer
    ): void {
        $project = \Mockery::mock('\ec5\DTO\ProjectDTO');
        $inputDetails = ['ref' => 'xxx', 'type' => 'text', 'regex' => $pattern];

        $rule = new RuleTextInput();
        $rule->setRules($inputDetails, $matchingAnswer, $project);
        $this->assertTrue(
            $rule->validate(['xxx' => $matchingAnswer]),
            "Expected '$matchingAnswer' to be accepted by '$pattern'"
        );
        $rule->resetErrors();

        $rule->setRules($inputDetails, $nonMatchingAnswer, $project);
        $this->assertFalse(
            $rule->validate(['xxx' => $nonMatchingAnswer]),
            "Expected '$nonMatchingAnswer' to be rejected by '$pattern'"
        );

        \Mockery::close();
    }

    public function test_upload_regex_is_skipped_for_empty_answer_and_absent_regex(): void
    {
        $project = \Mockery::mock('\ec5\DTO\ProjectDTO');

        // The regex rule is not implicit, so Laravel skips it for an empty answer.
        // Empty answers are optional; "required" is enforced separately.
        $rule = new RuleTextInput();
        $rule->setRules(['ref' => 'xxx', 'type' => 'text', 'regex' => '^[0-9]+$'], '', $project);
        $this->assertTrue($rule->validate(['xxx' => '']));
        $rule->resetErrors();

        // Empty string regex: no regex rule is added, so any answer is accepted.
        $rule = new RuleTextInput();
        $rule->setRules(['ref' => 'xxx', 'type' => 'text', 'regex' => ''], 'anything', $project);
        $this->assertTrue($rule->validate(['xxx' => 'anything']));
        $rule->resetErrors();

        // Null regex: no regex rule is added, so any answer is accepted.
        $rule = new RuleTextInput();
        $rule->setRules(['ref' => 'xxx', 'type' => 'text', 'regex' => null], 'anything', $project);
        $this->assertTrue($rule->validate(['xxx' => 'anything']));

        \Mockery::close();
    }

    public function test_upload_regex_rejects_non_string_answer(): void
    {
        $project = \Mockery::mock('\ec5\DTO\ProjectDTO');

        $rule = new RuleTextInput();
        $rule->setRules(['ref' => 'xxx', 'type' => 'text', 'regex' => '^[0-9]+$'], '12', $project);
        $this->assertFalse($rule->validate(['xxx' => ['12']]));

        \Mockery::close();
    }

    public function test_malformed_regex_returns_ec5_392(): void
    {
        $this->inputDetails['regex'] = '[0-9';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals(['ec5_392'], $this->validator->errors()[$this->inputDetails['ref']]);
        $this->validator->resetErrors();
    }

    public function test_legacy_malformed_regexes_return_ec5_392(): void
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
