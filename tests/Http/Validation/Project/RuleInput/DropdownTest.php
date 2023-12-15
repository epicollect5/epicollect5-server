<?php

namespace Tests\Http\Validation\Project\RuleInput;

use ec5\Http\Validation\Project\RuleInput;
use Tests\Http\Validation\Project\RuleInput\InputTest;

class DropdownTest extends InputTest
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

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->type = 'dropdown';
        $this->reset();
    }

    public function reset()
    {
        $this->possibleAnswers = [
            [
                "answer" => "jump",
                "answer_ref" => "58d92c0650433"
            ],
            [
                "answer" => "dont jump",
                "answer_ref" => "58d92c2b50435"
            ]
        ];
        $this->jumps = [
            [
                "to" => "END",
                "when" => "IS",
                "answer_ref" => "58d92c0650433"
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

        // Invalid 'answer_ref' possible answer
        $this->inputDetails['jumps'][0]['answer_ref'] = 'aaa';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

        //invalid extra key in jump
        $this->inputDetails['jumps'][0]['extra'] = 'ciao';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->reset();

    }

    public function test_max_possible_answer_length()
    {

        //valid length
        $this->inputDetails['possible_answers'][1] = [
            "answer" => "ciao",
            "answer_ref" => "58d92c2b50435"
        ];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());

        //possibe answer too long
        $this->inputDetails['possible_answers'][1] = [
            "answer" => "GaVv4UVrLegiZEGEqpfmiBJJuzvgtbiJqWonQjIXYu0Vl8nxj0VyOIwhQp9LM462PaBSTQp2u8PBlEu76imOK9gI7wkU0jEc6drzpHqBrbqgoKsCfr5hwchPOkabd94Qi3RePE8OM4pVcrZnHI7uBoznZ5ztmqWipwfIjjALcmt9BOCEGZ3mWVtA4WxWmYHVUIivHGGRvU4RFFUpHheUG7aBe0qaSDjgAjIVhNUd0RgTUqZzAMwKQ4X1hTs",
            "answer_ref" => "58d92c2b50435"
        ];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);

        $this->assertTrue($this->validator->hasErrors());

        $this->assertArrayHasKey('question', $this->validator->errors);
        $this->assertContains('ec5_341', $this->validator->errors['xxx_123456789abcd']);

        $this->validator->resetErrors();
        $this->reset();
    }

    public function test_max_possible_answer_ref_length()
    {

        //valid length
        $this->inputDetails['possible_answers'][1] = [
            "answer" => "ciao",
            "answer_ref" => "58d92c2b50435"
        ];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());

        //possibe answer ref too long
        $this->inputDetails['possible_answers'][1] = [
            "answer" => "ciao",
            "answer_ref" => "58d92c2b504350"
        ];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);

        $this->assertTrue($this->validator->hasErrors());

        $this->assertArrayHasKey('question', $this->validator->errors);
        $this->assertContains('ec5_355', $this->validator->errors['xxx_123456789abcd']);

        $this->validator->resetErrors();
        $this->reset();

        //possibe answer ref too short
        $this->inputDetails['possible_answers'][1] = [
            "answer" => "ciao",
            "answer_ref" => "56"
        ];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);

        $this->assertTrue($this->validator->hasErrors());

        $this->assertArrayHasKey('question', $this->validator->errors);
        $this->assertContains('ec5_355', $this->validator->errors['xxx_123456789abcd']);

        $this->validator->resetErrors();
        $this->reset();
    }

    public function test_max_number_of_possible_answers()
    {
        $limit = config('epicollect.limits.possible_answers_limit');
        $this->inputDetails['possible_answers'] = [];
        $this->inputDetails['jumps'] = [];

        for ($i = 0; $i < $limit; $i++) {
            //valid length
            $this->inputDetails['possible_answers'][$i] = [
                "answer" => $i . ' option',
                "answer_ref" => uniqid()
            ];
        }

        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());

        //too many possible answers
        $this->inputDetails['possible_answers'][$limit] = [
            "answer" => $limit . ' option',
            "answer_ref" => uniqid()
        ];

        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());

        $this->assertArrayHasKey('question', $this->validator->errors);
        $this->assertContains('ec5_340', $this->validator->errors['xxx_123456789abcd']);

        //no possible answers
        $this->validator->resetErrors();
        $this->inputDetails['possible_answers'] = [];
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertArrayHasKey('question', $this->validator->errors);
        $this->assertContains('ec5_338', $this->validator->errors['xxx_123456789abcd']);

        $this->validator->resetErrors();
        $this->reset();
    }

    public function test_default_for_possible_answers()
    {
        //valid
        $this->inputDetails['default'] = '58d92c0650433';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());

        //invalid
        $this->inputDetails['default'] = 'xxx';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertArrayHasKey('question', $this->validator->errors);
        $this->assertContains('ec5_339', $this->validator->errors['xxx_123456789abcd']);

    }

}
