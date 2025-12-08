<?php

namespace Tests\Http\Validation\Project\RuleInput;

use ec5\Http\Validation\Project\RuleInput;
use Mockery;
use Tests\TestCase;

abstract class InputTest extends TestCase
{
    protected mixed $project;
    protected mixed $validator;
    protected array $inputDetails;
    protected string $parentRef;
    protected string $type;
    protected array $jumps = [];
    protected array $possibleAnswers = [];

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->validator = new RuleInput();
        $this->project = Mockery::mock('\ec5\DTO\ProjectDTO');
    }

    /**
     * Reset test conditions
     *
     */
    protected function reset(): void
    {
        $this->inputDetails = [
            'ref' => 'xxx_123456789abcd',
            'type' => $this->type,
            'uniqueness' => 'none',
            'question' => 'a question',
            'default' => '',
            'regex' => '',
            'jumps' => $this->jumps,
            'possible_answers' => $this->possibleAnswers,
            'min' => '',
            'max' => ''
        ];

        $this->parentRef = 'xxx';
    }

    public function tearDown(): void
    {
        // Close all mocks before PHPUnit cleans up
        Mockery::close();
        parent::tearDown();
    }
}
