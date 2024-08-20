<?php

namespace Tests\Http\Validation\Project\RuleInput;

use ec5\Http\Validation\Project\RuleInput;
use Tests\TestCase;

class InputTest extends TestCase
{
    /**
     * @var \ec5\DTO\ProjectDTO
     */
    protected $project;
    /**
     * @var RuleInput
     */
    protected $validator;

    /**
     * @var array
     */
    protected $inputDetails;

    protected $parentRef;

    protected $type;
    protected $jumps = [];
    protected $possibleAnswers = [];

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->validator = new RuleInput();
        $this->project = \Mockery::mock('\ec5\DTO\ProjectDTO');
    }

    /**
     * Reset test conditions
     *
     */
    protected function reset()
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
}
