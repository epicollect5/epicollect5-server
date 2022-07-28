<?php
namespace Tests\Project\Inputs;

use ec5\Http\Validation\Project\RuleInput;
use Tests\TestCase;

abstract class InputTest extends TestCase
{

    /*
    |--------------------------------------------------------------------------
    | InputTest
    |--------------------------------------------------------------------------
    |
    | This test is abstract and so is never instantiated
    |
    */

    /**
     * @var \ec5\Models\Projects\Project
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

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->validator = new RuleInput();
        $this->project = \Mockery::mock('\ec5\Models\Projects\Project');
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

    public function testRef()
    {
        // Valid ref
        $this->inputDetails['ref'] = 'xxx_123456789abcd';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid ref
        $this->inputDetails['ref'] = 'xxx_xxx';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function testType()
    {
        // Valid type
        $this->inputDetails['type'] = $this->type;
        $this->validator->validate($this->inputDetails);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid type
        $this->inputDetails['type'] = 'xxx';
        $this->validator->validate($this->inputDetails);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        $this->reset();
    }

}
