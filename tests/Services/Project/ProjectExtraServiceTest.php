<?php

namespace Tests\Services\Project;

use ec5\Services\Project\ProjectExtraService;
use Tests\TestCase;

class ProjectExtraServiceTest extends TestCase
{
    private ProjectExtraService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProjectExtraService();
    }

    public function test_it_generates_extra_structure_with_correct_nesting()
    {
        $projectDefinition = [
            'project' => [
                'ref' => 'project-ref',
                'name' => 'Test Project',
                'slug' => 'test-project',
                'access' => 'public',
                'status' => 'active',
                'visibility' => 'listed',
                'small_description' => '',
                'description' => '',
                'category' => 'general',
                'entries_limits' => [],
                'forms' => [
                    [
                        'ref' => 'form-ref',
                        'name' => 'Form 1',
                        'slug' => 'form-1',
                        'inputs' => [
                            [
                                'ref' => 'text-input',
                                'type' => 'text',
                                'question' => 'Text Question',
                                'group' => [],
                                'branch' => []
                            ],
                            [
                                'ref' => 'group-input',
                                'type' => 'group',
                                'question' => 'Group Question',
                                'group' => [
                                    [
                                        'ref' => 'mc-in-group',
                                        'type' => 'radio',
                                        'question' => 'MC in Group',
                                        'possible_answers' => [
                                            ['answer_ref' => 'a1', 'answer' => 'Option 1']
                                        ]
                                    ],
                                    [
                                        'ref' => 'branch-in-group',
                                        'type' => 'branch',
                                        'question' => 'Branch in Group',
                                        'branch' => [
                                            [
                                                'ref' => 'mc-in-nested-branch',
                                                'type' => 'dropdown',
                                                'question' => 'MC in Nested Branch',
                                                'possible_answers' => [
                                                    ['answer_ref' => 'b1', 'answer' => 'Opt B1']
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                'branch' => []
                            ],
                            [
                                'ref' => 'branch-input',
                                'type' => 'branch',
                                'question' => 'Branch Question',
                                'branch' => [
                                    [
                                        'ref' => 'group-in-branch',
                                        'type' => 'group',
                                        'question' => 'Group in Branch',
                                        'group' => [
                                            [
                                                'ref' => 'mc-in-nested-group',
                                                'type' => 'checkbox',
                                                'question' => 'MC in Nested Group',
                                                'possible_answers' => [
                                                    ['answer_ref' => 'c1', 'answer' => 'Opt C1']
                                                ]
                                            ]
                                        ],
                                        'branch' => []
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $extra = $this->service->generateExtraStructure($projectDefinition);

        // 1. Verify Flattening (Requirement 1)
        // All inputs in the root 'inputs' key should have empty group/branch arrays
        foreach ($extra['inputs'] as $inputRef => $inputData) {
            $this->assertIsArray($inputData['data']['group']);
            $this->assertCount(0, $inputData['data']['group'], "Input $inputRef should have empty group");
            $this->assertIsArray($inputData['data']['branch']);
            $this->assertCount(0, $inputData['data']['branch'], "Input $inputRef should have empty branch");
        }

        $formExtra = $extra['forms']['form-ref'];

        // 2. Verify MC in top-level Group goes to Form level (Requirement 3)
        $this->assertContains('mc-in-group', $formExtra['lists']['multiple_choice_inputs']['form']['order']);
        $this->assertArrayHasKey('mc-in-group', $formExtra['lists']['multiple_choice_inputs']['form']);

        // 3. Verify Branch-in-Group (Requirement 2)
        $this->assertArrayHasKey('branch-in-group', $formExtra['branch']);
        $this->assertContains('mc-in-nested-branch', $formExtra['branch']['branch-in-group']);

        // 4. Verify MC in Branch-in-Group goes to branch bucket
        $branchMc = json_decode(json_encode($formExtra['lists']['multiple_choice_inputs']['branch']), true);
        $this->assertArrayHasKey('branch-in-group', $branchMc);
        $this->assertContains('mc-in-nested-branch', $branchMc['branch-in-group']['order']);

        // 5. Verify Group-in-Branch (Requirement 2 & 3)
        $this->assertArrayHasKey('group-in-branch', $formExtra['group']);

        // 6. Verify MC in Group-in-Branch goes to the correct branch bucket (Requirement 3)
        // Note: The logic adds it to $thisBranchMcOrder/Entries which belongs to the parent branch 'branch-input'
        $this->assertArrayHasKey('branch-input', $branchMc);
        $this->assertContains('mc-in-nested-group', $branchMc['branch-input']['order']);
        $this->assertArrayHasKey('mc-in-nested-group', $branchMc['branch-input']);
    }

    public function test_it_generates_extra_structure_correctly_for_a_simple_project()
    {
        $mockProject = [
            'project' => [
                'ref' => 'pro_123',
                'name' => 'Test Project',
                'slug' => 'test-project',
                'access' => 'public',
                'status' => 'active',
                'visibility' => 'listed',
                'small_description' => '',
                'description' => '',
                'category' => 'social',
                'entries_limits' => [],
                'forms' => [
                    [
                        'ref' => 'for_123',
                        'name' => 'Form 1',
                        'slug' => 'form-1',
                        'inputs' => [
                            [
                                'ref' => 'inp_1',
                                'type' => 'text',
                                'question' => 'Question 1',
                                'group' => [],
                                'branch' => []
                            ],
                            [
                                'ref' => 'inp_2',
                                'type' => 'location',
                                'question' => 'Location 1',
                                'group' => [],
                                'branch' => []
                            ],
                            [
                                'ref' => 'inp_3',
                                'type' => 'radio',
                                'question' => 'Radio 1',
                                'possible_answers' => [
                                    ['answer' => 'Yes', 'answer_ref' => 'ans_1'],
                                    ['answer' => 'No', 'answer_ref' => 'ans_2']
                                ],
                                'group' => [],
                                'branch' => []
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->service->generateExtraStructure($mockProject);

        $this->assertEquals('pro_123', $result['project']['details']['ref']);
        $this->assertArrayHasKey('for_123', $result['forms']);
        $this->assertContains('inp_1', $result['forms']['for_123']['inputs']);
        $this->assertContains('inp_2', $result['forms']['for_123']['inputs']);
        $this->assertContains('inp_3', $result['forms']['for_123']['inputs']);

        // Check location inputs
        $this->assertCount(1, $result['forms']['for_123']['lists']['location_inputs']);
        $this->assertEquals('inp_2', $result['forms']['for_123']['lists']['location_inputs'][0]['input_ref']);

        // Check multiple choice inputs
        $this->assertContains('inp_3', $result['forms']['for_123']['lists']['multiple_choice_inputs']['form']['order']);
        $this->assertEquals('Yes', $result['forms']['for_123']['lists']['multiple_choice_inputs']['form']['inp_3']['possible_answers']['ans_1']);

        // Check inputs extra
        $this->assertEquals('Question 1', $result['inputs']['inp_1']['data']['question']);
    }

    public function test_it_handles_groups_and_nested_location_mc_inputs()
    {
        $projectWithGroup = [
            'project' => [
                'ref' => 'pro_123',
                'name' => 'Test Project',
                'slug' => 'test-project',
                'access' => 'public',
                'status' => 'active',
                'visibility' => 'listed',
                'category' => 'social',
                'small_description' => '',
                'description' => '',
                'entries_limits' => [],
                'forms' => [
                    [
                        'ref' => 'for_123',
                        'name' => 'Form 1',
                        'slug' => 'form-1',
                        'inputs' => [
                            [
                                'ref' => 'grp_1',
                                'type' => 'group',
                                'question' => 'Group 1',
                                'group' => [
                                    [
                                        'ref' => 'inp_g1',
                                        'type' => 'location',
                                        'question' => 'Group Location',
                                        'group' => [],
                                        'branch' => []
                                    ],
                                    [
                                        'ref' => 'inp_g2',
                                        'type' => 'checkbox',
                                        'question' => 'Group Checkbox',
                                        'possible_answers' => [
                                            ['answer' => 'A', 'answer_ref' => 'ans_a']
                                        ],
                                        'group' => [],
                                        'branch' => []
                                    ]
                                ],
                                'branch' => []
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->service->generateExtraStructure($projectWithGroup);

        $this->assertEquals(['inp_g1', 'inp_g2'], $result['forms']['for_123']['group']['grp_1']);
        $this->assertCount(1, $result['forms']['for_123']['lists']['location_inputs']);
        $this->assertEquals('inp_g1', $result['forms']['for_123']['lists']['location_inputs'][0]['input_ref']);

        // MC inputs in a top-level group go into the *form* bucket
        $this->assertContains('inp_g2', $result['forms']['for_123']['lists']['multiple_choice_inputs']['form']['order']);
        $this->assertArrayHasKey('inp_g2', $result['forms']['for_123']['lists']['multiple_choice_inputs']['form']);
    }

    public function test_it_handles_branches_and_nested_inputs()
    {
        $projectWithBranch = [
            'project' => [
                'ref' => 'pro_123',
                'name' => 'Test Project',
                'slug' => 'test-project',
                'access' => 'public',
                'status' => 'active',
                'visibility' => 'listed',
                'category' => 'social',
                'small_description' => '',
                'description' => '',
                'entries_limits' => [],
                'forms' => [
                    [
                        'ref' => 'for_123',
                        'name' => 'Form 1',
                        'slug' => 'form-1',
                        'inputs' => [
                            [
                                'ref' => 'bra_1',
                                'type' => 'branch',
                                'question' => 'Branch 1',
                                'branch' => [
                                    [
                                        'ref' => 'inp_b1',
                                        'type' => 'location',
                                        'question' => 'Branch Location',
                                        'group' => [],
                                        'branch' => []
                                    ],
                                    [
                                        'ref' => 'inp_b2',
                                        'type' => 'dropdown',
                                        'question' => 'Branch Dropdown',
                                        'possible_answers' => [
                                            ['answer' => 'Opt 1', 'answer_ref' => 'ans_o1']
                                        ],
                                        'group' => [],
                                        'branch' => []
                                    ]
                                ],
                                'group' => []
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->service->generateExtraStructure($projectWithBranch);

        $this->assertEquals(['inp_b1', 'inp_b2'], $result['forms']['for_123']['branch']['bra_1']);

        // Location in branch: PHP uses parent branch ref as input_ref, and location ref as branch_ref
        $this->assertCount(1, $result['forms']['for_123']['lists']['location_inputs']);
        $this->assertEquals('bra_1', $result['forms']['for_123']['lists']['location_inputs'][0]['input_ref']);
        $this->assertEquals('inp_b1', $result['forms']['for_123']['lists']['location_inputs'][0]['branch_ref']);

        // MC in branch goes to branch bucket keyed by branch ref
        $branchMc = (array)$result['forms']['for_123']['lists']['multiple_choice_inputs']['branch'];
        $this->assertArrayHasKey('bra_1', $branchMc);
        $this->assertContains('inp_b2', $branchMc['bra_1']['order']);
        $this->assertEquals('Branch Dropdown', $branchMc['bra_1']['inp_b2']['question']);
    }

    public function test_it_handles_group_inside_branch()
    {
        $projectWithGroupInBranch = [
            'project' => [
                'ref' => 'pro_123',
                'name' => 'Test Project',
                'slug' => 'test-project',
                'access' => 'public',
                'status' => 'active',
                'visibility' => 'listed',
                'small_description' => '',
                'description' => '',
                'category' => 'social',
                'entries_limits' => [],
                'forms' => [
                    [
                        'ref' => 'for_123',
                        'name' => 'Form 1',
                        'slug' => 'form-1',
                        'inputs' => [
                            [
                                'ref' => 'bra_1',
                                'type' => 'branch',
                                'question' => 'Branch 1',
                                'branch' => [
                                    [
                                        'ref' => 'grp_1',
                                        'type' => 'group',
                                        'question' => 'Group in Branch',
                                        'group' => [
                                            [
                                                'ref' => 'inp_bg1',
                                                'type' => 'location',
                                                'question' => 'BG Location',
                                                'group' => [],
                                                'branch' => []
                                            ]
                                        ],
                                        'branch' => []
                                    ]
                                ],
                                'group' => []
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->service->generateExtraStructure($projectWithGroupInBranch);

        $this->assertContains('grp_1', $result['forms']['for_123']['branch']['bra_1']);
        $this->assertContains('inp_bg1', $result['forms']['for_123']['group']['grp_1']);

        // Location in group in branch
        $this->assertCount(1, $result['forms']['for_123']['lists']['location_inputs']);
        // When location is in a group inside a branch:
        // current PHP logic: input_ref = location ref, branch_ref = parent group ref
        $this->assertEquals('inp_bg1', $result['forms']['for_123']['lists']['location_inputs'][0]['input_ref']);
        $this->assertEquals('grp_1', $result['forms']['for_123']['lists']['location_inputs'][0]['branch_ref']);
    }

    public function test_it_handles_branch_inside_group()
    {
        $projectWithBranchInGroup = [
            'project' => [
                'ref' => 'pro_123',
                'name' => 'Test Project',
                'slug' => 'test-project',
                'access' => 'public',
                'status' => 'active',
                'visibility' => 'listed',
                'small_description' => '',
                'description' => '',
                'category' => 'social',
                'entries_limits' => [],
                'forms' => [
                    [
                        'ref' => 'for_123',
                        'name' => 'Form 1',
                        'slug' => 'form-1',
                        'inputs' => [
                            [
                                'ref' => 'grp_1',
                                'type' => 'group',
                                'question' => 'Group 1',
                                'group' => [
                                    [
                                        'ref' => 'bra_g1',
                                        'type' => 'branch',
                                        'question' => 'Branch in Group',
                                        'branch' => [
                                            [
                                                'ref' => 'inp_gb1',
                                                'type' => 'location',
                                                'question' => 'GB Location',
                                                'group' => [],
                                                'branch' => []
                                            ],
                                            [
                                                'ref' => 'inp_gb2',
                                                'type' => 'radio',
                                                'question' => 'GB Radio',
                                                'possible_answers' => [
                                                    ['answer' => 'Yes', 'answer_ref' => 'ans_y'],
                                                    ['answer' => 'No', 'answer_ref' => 'ans_n']
                                                ],
                                                'group' => [],
                                                'branch' => []
                                            ]
                                        ],
                                    ]
                                ],
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->service->generateExtraStructure($projectWithBranchInGroup);

        // The branch ref should appear in the parent group's children
        $this->assertContains('bra_g1', $result['forms']['for_123']['group']['grp_1']);

        // Check the branch's children
        $allBranches = $result['forms']['for_123']['branch'];

        $this->assertArrayHasKey('bra_g1', $allBranches);
        // Using assertContains to verify children existence individually
        $this->assertContains('inp_gb1', $allBranches['bra_g1']);
        $this->assertContains('inp_gb2', $allBranches['bra_g1']);

        // Location inside branch inside group: input_ref = parent branch ref, branch_ref = location ref
        $this->assertCount(1, $result['forms']['for_123']['lists']['location_inputs']);
        $this->assertEquals('bra_g1', $result['forms']['for_123']['lists']['location_inputs'][0]['input_ref']);
        $this->assertEquals('inp_gb1', $result['forms']['for_123']['lists']['location_inputs'][0]['branch_ref']);

        // MC input inside branch inside group goes to branch MC bucket
        $branchMc = (array)$result['forms']['for_123']['lists']['multiple_choice_inputs']['branch'];
        $this->assertArrayHasKey('bra_g1', $branchMc);
        $this->assertContains('inp_gb2', $branchMc['bra_g1']['order']);
        $this->assertEquals('GB Radio', $branchMc['bra_g1']['inp_gb2']['question']);
    }

    public function test_it_handles_multiple_forms()
    {
        $projectDefinition = [
            'project' => [
                'ref' => 'project-ref',
                'name' => 'Multi Form Project',
                'slug' => 'multi-form',
                'access' => 'public',
                'status' => 'active',
                'visibility' => 'listed',
                'small_description' => '',
                'description' => '',
                'category' => 'general',
                'entries_limits' => [],
                'forms' => [
                    [
                        'ref' => 'form-1',
                        'name' => 'Form 1',
                        'slug' => 'form-1',
                        'inputs' => [
                            ['ref' => 'f1-input1', 'type' => 'text', 'question' => 'Q1']
                        ]
                    ],
                    [
                        'ref' => 'form-2',
                        'name' => 'Form 2',
                        'slug' => 'form-2',
                        'inputs' => [
                            ['ref' => 'f2-input1', 'type' => 'text', 'question' => 'Q2']
                        ]
                    ]
                ]
            ]
        ];

        $extra = $this->service->generateExtraStructure($projectDefinition);

        $this->assertArrayHasKey('form-1', $extra['forms']);
        $this->assertArrayHasKey('form-2', $extra['forms']);
        $this->assertCount(2, $extra['project']['forms']);
        $this->assertEquals(['form-1', 'form-2'], $extra['project']['forms']);
    }
}
