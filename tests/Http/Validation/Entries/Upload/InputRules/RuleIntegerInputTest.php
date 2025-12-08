<?php

namespace Tests\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleIntegerInput;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\RandomException;
use Tests\TestCase;

class RuleIntegerInputTest extends TestCase
{
    protected ProjectDTO $project;
    protected RuleIntegerInput $ruleIntegerInput;
    protected array $inputDetails;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();

        $this->ruleIntegerInput = new RuleIntegerInput();

        $this->reset();

        $this->project = \Mockery::mock('\ec5\DTO\ProjectDTO');
    }

    /**
     * Reset test conditions
     */
    private function reset(): void
    {
        $this->inputDetails = [
            'ref' => 'xxx',
            'min' => '',
            'max' => '',
            'regex' => '',
            'type' => 'decimal'
        ];
    }

    /**
     *
     * @throws RandomException
     */
    public function test_is_int()
    {
        // Generate 100 random integers between PHP_INT_MIN and PHP_INT_MAX
        for ($i = 0; $i < 100; $i++) {
            $answer = random_int(PHP_INT_MIN, PHP_INT_MAX);

            $this->ruleIntegerInput->setRules($this->inputDetails, $answer, $this->project);

            $data = [$this->inputDetails['ref'] => $answer];
            $this->ruleIntegerInput->validate($data);

            $this->assertFalse(
                $this->ruleIntegerInput->hasErrors(),
                "Failed asserting that '{$answer}' is a valid integer input."
            );

            $this->ruleIntegerInput->resetErrors();
        }

        $this->reset();
    }


    public function test_is_not_int()
    {
        $invalidAnswers = [
            100.1,
            -42.99,
            '£',
            'dd',
            '3.14',
            null,
            true,
            false,
            '123abc',
            '0.0',
            '1e3',
            ' 42 ', // whitespace
        ];

        foreach ($invalidAnswers as $answer) {
            $this->ruleIntegerInput->setRules($this->inputDetails, $answer, $this->project);

            $data = [$this->inputDetails['ref'] => $answer];
            $this->ruleIntegerInput->validate($data);

            $this->assertTrue(
                $this->ruleIntegerInput->hasErrors(),
                "Failed asserting that '{$answer}' is rejected as an invalid integer input."
            );

            $this->ruleIntegerInput->resetErrors();
        }

        $this->reset();
    }



    public function test_min_max_answer_valid()
    {
        // Valid answer
        $answer = 100;
        // Sex min/max
        $this->inputDetails['min'] = '50';
        $this->inputDetails['max'] = '200';

        $this->ruleIntegerInput->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->ruleIntegerInput->validate($data);
        $this->assertFalse($this->ruleIntegerInput->hasErrors());
        $this->ruleIntegerInput->resetErrors();

        $this->reset();
    }

    /**
     *
     */
    public function test_min_max_answer_too_low()
    {
        // Answer too low
        $answer = 0;
        // Sex min/max
        $this->inputDetails['min'] = '50';
        $this->inputDetails['max'] = '200';

        $this->ruleIntegerInput->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->ruleIntegerInput->validate($data);
        $this->assertTrue($this->ruleIntegerInput->hasErrors());
        $this->ruleIntegerInput->resetErrors();

        $this->reset();
    }

    /**
     *
     */
    public function test_min_max_answer_too_high()
    {
        // Answer too high
        $answer = 210;
        // Sex min/max
        $this->inputDetails['min'] = '50';
        $this->inputDetails['max'] = '200';

        $this->ruleIntegerInput->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->ruleIntegerInput->validate($data);
        $this->assertTrue($this->ruleIntegerInput->hasErrors());
        $this->ruleIntegerInput->resetErrors();
    }

    #[DataProvider('outOfBoundsIntDataProvider')]
    public function test_answer_out_of_bounds($min, $max, $answer)
    {
        $this->inputDetails['min'] = (string) $min;
        $this->inputDetails['max'] = (string) $max;

        $this->ruleIntegerInput->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->ruleIntegerInput->validate($data);

        if ($answer < $min) {
            echo "Caught: {$answer} is lower than min {$min}\n";
        } elseif ($answer > $max) {
            echo "Caught: {$answer} is greater than max {$max}\n";
        }

        $this->assertTrue($this->ruleIntegerInput->hasErrors(), "Expected error for value={$answer}, min={$min}, max={$max}");

        $this->ruleIntegerInput->resetErrors();
        $this->reset();
    }
}
