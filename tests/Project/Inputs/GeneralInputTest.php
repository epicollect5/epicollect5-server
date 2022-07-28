<?php
namespace Tests\Project\Inputs;

use ec5\Http\Validation\Project\RuleInput;

class GeneralInputTest extends InputTest
{

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

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->type = 'text';
        $this->reset();
    }

    public function testJumps()
    {
        // Valid jumps
        $this->inputDetails['jumps'] = [['answer_ref' => null, 'to' => 'xxx', 'when' => 'ALL']];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid jumps
        $this->inputDetails['jumps'] = [['answer_ref' => null, 'to' => 'xxx', 'when' => 'NO_ANSWER_GIVEN']];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid jumps
        $this->inputDetails['jumps'] = [['answer_ref' => 'xxx', 'to' => 'xxx', 'when' => 'IS_NOT']];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        $this->reset();
    }
}
