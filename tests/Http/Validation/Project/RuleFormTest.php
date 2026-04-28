<?php

namespace Tests\Http\Validation\Project;

use ec5\Http\Validation\Project\RuleForm;
use Tests\TestCase;

class RuleFormTest extends TestCase
{
    protected RuleForm $validator;
    protected array $form;

    /**
     *
     */
    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();

        $this->resetForm();

        $this->validator = new RuleForm();
    }

    public function resetForm(): void
    {
        // Form has 3 inputs
        // Input 1 has jump to Input 3
        // Input 2 has jump to END
        // Input 3 has no jump
        $this->form = [
            "ref" => "3e29e228288e41548adfd4907ee5f49b_58d92bf350430",
            "name" => "jump form",
            "slug" => "jump-form",
            "type" => "hierarchy",
            "inputs" => [
                [
                    "max" => null,
                    "min" => null,
                    "ref" => "3e29e228288e41548adfd4907ee5f49b_58d92bf350430_58d92bf950431",
                    "type" => "text",
                    "group" => [],
                    "jumps" => [
                        [
                            "to" => "3e29e228288e41548adfd4907ee5f49b_58d92bf350430_58d92c0950434",
                            "when" => "ALL",
                            "answer_ref" => null
                        ]
                    ],
                    "regex" => null,
                    "branch" => [],
                    "verify" => false,
                    "default" => null,
                    "is_title" => false,
                    "question" => "text-jump",
                    "uniqueness" => "none",
                    "is_required" => false,
                    "datetime_format" => null,
                    "possible_answers" => [],
                    "set_to_current_datetime" => false
                ],
                [
                    "max" => null,
                    "min" => null,
                    "ref" => "3e29e228288e41548adfd4907ee5f49b_58d92bf350430_58d92c0650432",
                    "type" => "radio",
                    "group" => [],
                    "jumps" => [
                        [
                            "to" => "END",
                            "when" => "IS",
                            "answer_ref" => "58d92c0650433"
                        ]
                    ],
                    "regex" => null,
                    "branch" => [],
                    "verify" => false,
                    "default" => "",
                    "is_title" => false,
                    "question" => "radio-jump",
                    "uniqueness" => "none",
                    "is_required" => false,
                    "datetime_format" => null,
                    "possible_answers" => [
                        [
                            "answer" => "jump",
                            "answer_ref" => "58d92c0650433"
                        ],
                        [
                            "answer" => "dont jump",
                            "answer_ref" => "58d92c2b50435"
                        ]
                    ],
                    "set_to_current_datetime" => false
                ],
                [
                    "max" => null,
                    "min" => null,
                    "ref" => "3e29e228288e41548adfd4907ee5f49b_58d92bf350430_58d92c0950434",
                    "type" => "text",
                    "group" => [],
                    "jumps" => [],
                    "regex" => null,
                    "branch" => [],
                    "verify" => false,
                    "default" => null,
                    "is_title" => false,
                    "question" => "text-no-jump",
                    "uniqueness" => "none",
                    "is_required" => false,
                    "datetime_format" => null,
                    "possible_answers" => [],
                    "set_to_current_datetime" => false
                ],
                [
                    "max" => null,
                    "min" => null,
                    "ref" => "3e29e228288e41548adfd4907ee5f49b_58d92bf350430_58d92c0650433",
                    "type" => "group",
                    "group" => [
                        [
                            "max" => null,
                            "min" => null,
                            "ref" => "3e29e228288e41548adfd4907ee5f49b_58d92bf350430_58d92c0650434",
                            "type" => "radio",
                            "group" => [],
                            "jumps" => [],
                            "regex" => null,
                            "branch" => [],
                            "verify" => false,
                            "default" => "",
                            "is_title" => false,
                            "question" => "radio-jump",
                            "uniqueness" => "none",
                            "is_required" => false,
                            "datetime_format" => null,
                            "possible_answers" => [
                                [
                                    "answer" => "jump",
                                    "answer_ref" => "58d92c0650434"
                                ],
                                [
                                    "answer" => "dont jump",
                                    "answer_ref" => "58d92c2b50436"
                                ]
                            ],
                            "set_to_current_datetime" => false
                        ]
                    ],
                    "jumps" => [],
                    "regex" => null,
                    "branch" => [],
                    "verify" => false,
                    "default" => "",
                    "is_title" => false,
                    "question" => "group",
                    "uniqueness" => "none",
                    "is_required" => false,
                    "datetime_format" => null,
                    "possible_answers" => [],
                    "set_to_current_datetime" => false
                ]
            ]
        ];
    }

    /**
     *
     */
    public function test_jumps()
    {

        // Valid jumps
        $this->validator->validateJumps($this->form['inputs']);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->resetForm();

        // Invalid jumps - invalid ref on a jump (just use first input, first jump)
        $this->form['inputs'][0]['jumps'][0]['to'] = 'aaa';
        $this->validator->validateJumps($this->form['inputs']);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->resetForm();

        // Invalid jumps - referencing a previous input (make second input reference first)
        $this->form['inputs'][1]['jumps'][0]['to'] = $this->form['inputs'][0]['ref'];
        $this->validator->validateJumps($this->form['inputs']);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->resetForm();

        // Invalid jumps - referencing the next input (make first input reference second)
        $this->form['inputs'][0]['jumps'][0]['to'] = $this->form['inputs'][1]['ref'];
        $this->validator->validateJumps($this->form['inputs']);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->resetForm();

    }

    /**
     *
     */
    public function test_group_jumps()
    {

        // Valid - no group jumps jumps
        $this->validator->validateJumps($this->form['inputs']);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->resetForm();

        // Invalid jumps - any jump in a group is invalid
        $this->form['inputs'][3]['group'][0]['jumps'] = [
            [
                "to" => "END",
                "when" => "IS",
                "answer_ref" => "58d92c0650434"
            ]
        ];

        $this->validator->validateJumps($this->form['inputs']);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
        $this->resetForm();

    }

    public function test_terminal_inputs_cannot_jump_to_end()
    {
        $this->form['inputs'][3]['jumps'] = [
            [
                "to" => "END",
                "when" => "ALL",
                "answer_ref" => null
            ]
        ];

        $this->validator->validateJumps($this->form['inputs']);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals(['ec5_264'], $this->validator->errors()[$this->form['inputs'][3]['ref']]);

        $this->validator->resetErrors();
        $this->resetForm();

        $this->form['inputs'][1]['type'] = 'branch';
        $this->form['inputs'][1]['branch'] = [
            [
                "max" => null,
                "min" => null,
                "ref" => "branch_input_1",
                "type" => "text",
                "group" => [],
                "jumps" => [],
                "regex" => null,
                "branch" => [],
                "verify" => false,
                "default" => null,
                "is_title" => false,
                "question" => "branch-text-1",
                "uniqueness" => "none",
                "is_required" => false,
                "datetime_format" => null,
                "possible_answers" => [],
                "set_to_current_datetime" => false
            ],
            [
                "max" => null,
                "min" => null,
                "ref" => "branch_input_2",
                "type" => "text",
                "group" => [],
                "jumps" => [
                    [
                        "to" => "END",
                        "when" => "ALL",
                        "answer_ref" => null
                    ]
                ],
                "regex" => null,
                "branch" => [],
                "verify" => false,
                "default" => null,
                "is_title" => false,
                "question" => "branch-text-2",
                "uniqueness" => "none",
                "is_required" => false,
                "datetime_format" => null,
                "possible_answers" => [],
                "set_to_current_datetime" => false
            ]
        ];

        $this->validator->validateJumps($this->form['inputs']);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertEquals(['ec5_264'], $this->validator->errors()['branch_input_2']);
    }
}
