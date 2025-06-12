<?php

namespace Tests\Http\Validation\Project\RuleInput;

class PhotoTest extends InputTest
{
    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->type = config('epicollect.strings.inputs_type.photo');
        $this->reset();
    }

    public function reset(): void
    {
        $this->possibleAnswers = [];
        $this->jumps = [
            [
                "to" => "END",
                "when" => "ALL",
                "answer_ref" => null
            ]
        ];

        parent::reset();
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
        // Valid jump with possible answer
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //invalid extra key in jump
        $this->inputDetails['jumps'][0]['extra'] = 'ciao';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //media types can only jump always (ALL)
        $this->inputDetails['jumps'][0]['when'] = 'IS';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals(
            [
            $this->inputDetails['ref'] => [
                0 => "ec5_207"
            ]],
            $this->validator->errors()
        );
        $this->validator->resetErrors();
        $this->reset();

        $this->inputDetails['jumps'][0]['when'] = 'IS_NOT';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals(
            [
            $this->inputDetails['ref'] => [
                0 => "ec5_207"
            ]],
            $this->validator->errors()
        );
        $this->validator->resetErrors();
        $this->reset();

        $this->inputDetails['jumps'][0]['when'] = 'NO_ANSWER_GIVEN';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals(
            [
            $this->inputDetails['ref'] => [
                0 => "ec5_207"
            ]],
            $this->validator->errors()
        );
        $this->validator->resetErrors();
        $this->reset();

    }

    public function test_possible_answers_must_be_empty()
    {
        $this->inputDetails['possible_answers'][1] = [
            "answer" => "ciao",
            "answer_ref" => "58d92c2b50435"
        ];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals([
            $this->inputDetails['ref'] => [
                0 => "ec5_398"
            ],
            "question" => [
                0 => "a question"
            ]
        ], $this->validator->errors());

        $this->validator->resetErrors();
        $this->reset();
    }
}
