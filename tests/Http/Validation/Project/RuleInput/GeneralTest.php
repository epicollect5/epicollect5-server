<?php

namespace Tests\Http\Validation\Project\RuleInput;

class GeneralTest extends InputTest
{
    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->type = 'text';
        $this->reset();
    }

    public function test_ref()
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

    public function test_type()
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

    public function test_jumps()
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
