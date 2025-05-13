<?php

namespace Tests\Http\Validation\Entries\Upload\InputRules;

use ec5\Http\Validation\Entries\Upload\InputRules\RuleIntegerInput;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RuleIntegerInputTest extends TestCase
{
    /**
     * @var \ec5\DTO\ProjectDTO
     */
    protected $project;
    /**
     * @var RuleIntegerInput
     */
    protected $validator;

    /**
     * @var array
     */
    protected $inputDetails;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();

        $this->validator = new RuleIntegerInput();

        $this->reset();

        $this->project = \Mockery::mock('\ec5\DTO\ProjectDTO');
    }

    /**
     * Reset test conditions
     */
    private function reset()
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
     */
    public function test_is_int()
    {
        // Valid answer
        $answer = 100;
        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Valid answer
        $answer = 0;
        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Valid answer
        $answer = -100;
        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        $this->reset();
    }

    /**
     *
     */
    public function test_is_not_int()
    {
        // Invalid answer
        $answer = 100.1;
        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid answer
        $answer = "£";
        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid answer
        $answer = "dd";
        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        $this->reset();
    }

    /**
     *
     */
    public function test_min_max_answer_valid()
    {
        // Valid answer
        $answer = 100;
        // Sex min/max
        $this->inputDetails['min'] = '50';
        $this->inputDetails['max'] = '200';

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

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

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

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

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    #[DataProvider('outOfBoundsIntDataProvider')]
    public function test_answer_out_of_bounds($min, $max, $answer)
    {
        $this->inputDetails['min'] = (string) $min;
        $this->inputDetails['max'] = (string) $max;

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);

        if ($answer < $min) {
            echo "Caught: {$answer} is lower than min {$min}\n";
        } elseif ($answer > $max) {
            echo "Caught: {$answer} is greater than max {$max}\n";
        }

        $this->assertTrue($this->validator->hasErrors(), "Expected error for value={$answer}, min={$min}, max={$max}");

        $this->validator->resetErrors();
        $this->reset();
    }
}
