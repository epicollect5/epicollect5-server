<?php

namespace Tests\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTextInput;
use Mockery;
use Tests\TestCase;

/**
 * Runs every unique question regex ever saved by an existing project through the upload
 * validation path, which converts them with ProjectRegex::toRule().
 *
 * Guards the fix that started picking a delimiter free of the pattern and switching to
 * UTF-8 matching: valid legacy patterns must not start returning ec5_392, and the
 * malformed ones saved while no gate existed must be rejected with ec5_392.
 */
class RuleTextInputLegacyRegexTest extends TestCase
{
    private const REF = 'xxx';

    private array $validPatterns;

    private array $malformedPatterns;

    public function setUp(): void
    {
        parent::setUp();

        $fixtures = dirname(__DIR__, 5) . '/Fixtures';
        $this->validPatterns = require $fixtures . '/legacy-question-regex-valid.php';
        $this->malformedPatterns = require $fixtures . '/legacy-question-regex-malformed.php';
    }

    public function test_valid_legacy_regexes_are_not_rejected_on_upload(): void
    {
        $project = Mockery::mock(ProjectDTO::class);

        foreach ($this->validPatterns as $pattern) {
            $rule = new RuleTextInput();
            $rule->setRules(['ref' => self::REF, 'type' => 'text', 'regex' => $pattern], 'answer', $project);
            $rule->validate([self::REF => 'answer']);

            $this->assertNotContains(
                'ec5_392',
                $rule->errors()[self::REF] ?? [],
                'Valid legacy regex returned ec5_392: ' . $pattern
            );
        }

        Mockery::close();
    }

    public function test_malformed_legacy_regexes_return_ec5_392_on_upload(): void
    {
        $project = Mockery::mock(ProjectDTO::class);

        foreach ($this->malformedPatterns as $pattern) {
            $rule = new RuleTextInput();
            $rule->setRules(['ref' => self::REF, 'type' => 'text', 'regex' => $pattern], 'answer', $project);
            $rule->validate([self::REF => 'answer']);

            $this->assertSame(
                ['ec5_392'],
                $rule->errors()[self::REF] ?? [],
                'Malformed legacy regex was not rejected with ec5_392: ' . $pattern
            );
        }

        Mockery::close();
    }
}
