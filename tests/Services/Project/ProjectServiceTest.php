<?php

namespace Tests\Services\Project;

use ec5\Services\Project\ProjectService;
use Tests\TestCase;

class ProjectServiceTest extends TestCase
{
    protected ProjectService $projectService;

    public function setUp(): void
    {
        parent::setUp();
        $this->projectService = new ProjectService();
    }

    public function test_sanitise_project_definition_for_download_sets_answer_ref_null_for_end_all_jumps()
    {
        // Sample project definition with jumps missing answer_ref
        $projectDefinition = [
            'project' => [
                'small_description' => 'Test project',
                'description' => 'Test description',
                'forms' => [
                    [
                        'name' => 'Form 1',
                        'inputs' => [
                            [
                                'type' => 'text',
                                'jumps' => [
                                    [
                                        'to' => 'END',
                                        'when' => 'ALL',
                                        // answer_ref missing
                                    ],
                                    [
                                        'to' => 'END',
                                        'when' => 'ALL',
                                        'answer_ref' => '', // empty string
                                    ],
                                    [
                                        'to' => 'END',
                                        'when' => 'ALL',
                                        'answer_ref' => 'some_ref', // already set, should not change
                                    ],
                                ],
                            ],
                            [
                                'type' => config('epicollect.strings.inputs_type.branch'),
                                'branch' => [
                                    [
                                        'ref' => 'branch_input_1',
                                        'jumps' => [
                                            [
                                                'to' => 'END',
                                                'when' => 'ALL',
                                                // answer_ref missing
                                            ],
                                            [
                                                'to' => 'END',
                                                'when' => 'ALL',
                                                'answer_ref' => '', // empty string
                                            ],
                                            [
                                                'to' => 'END',
                                                'when' => 'ALL',
                                                'answer_ref' => 'branch_ref', // already set
                                            ],
                                        ],
                                        'branch' => [],
                                        'group' => [],
                                    ],
                                    [
                                        'ref' => 'branch_input_2',
                                        'type' => 'text',
                                        'jumps' => [],
                                        'branch' => [],
                                        'group' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        // Check direct input jumps
        $this->assertNull($sanitised['project']['forms'][0]['inputs'][0]['jumps'][0]['answer_ref']);
        $this->assertNull($sanitised['project']['forms'][0]['inputs'][0]['jumps'][1]['answer_ref']);
        $this->assertEquals('some_ref', $sanitised['project']['forms'][0]['inputs'][0]['jumps'][2]['answer_ref']);

        // Check branch jumps
        $this->assertNull($sanitised['project']['forms'][0]['inputs'][1]['branch'][0]['jumps'][0]['answer_ref']);
        $this->assertNull($sanitised['project']['forms'][0]['inputs'][1]['branch'][0]['jumps'][1]['answer_ref']);
        $this->assertEquals('branch_ref', $sanitised['project']['forms'][0]['inputs'][1]['branch'][0]['jumps'][2]['answer_ref']);
    }

    public function test_sanitise_project_definition_for_download_handles_missing_jumps()
    {
        $projectDefinition = [
            'project' => [
                'small_description' => 'Test',
                'description' => 'Test',
                'forms' => [
                    [
                        'name' => 'Form 1',
                        'inputs' => [
                            [
                                'type' => 'text',
                                // no jumps
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        $this->assertEquals($projectDefinition['project']['forms'], $sanitised['project']['forms']);
    }

    public function test_sanitise_project_definition_for_download_handles_non_end_all_jumps()
    {
        $projectDefinition = [
            'project' => [
                'small_description' => 'Test',
                'description' => 'Test',
                'forms' => [
                    [
                        'name' => 'Form 1',
                        'inputs' => [
                            [
                                'type' => 'text',
                                'jumps' => [
                                    [
                                        'to' => 'next',
                                        'when' => 'SOME',
                                        // should not set answer_ref
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        $this->assertArrayNotHasKey('answer_ref', $sanitised['project']['forms'][0]['inputs'][0]['jumps'][0]);
    }

    public function test_sanitise_project_definition_for_download_removes_has_valid_destination()
    {
        $projectDefinition = [
            'project' => [
                'small_description' => 'Test',
                'description' => 'Test',
                'forms' => [
                    [
                        'name' => 'Form 1',
                        'inputs' => [
                            [
                                'ref' => 'input_1',
                                'type' => 'text',
                                'jumps' => [
                                    [
                                        'to' => 'END',
                                        'when' => 'ALL',
                                        'has_valid_destination' => true,
                                    ],
                                ],
                                'branch' => [],
                                'group' => [],
                            ],
                            [
                                'ref' => 'input_2',
                                'type' => 'text',
                                'jumps' => [],
                                'branch' => [],
                                'group' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        $this->assertArrayNotHasKey('has_valid_destination', $sanitised['project']['forms'][0]['inputs'][0]['jumps'][0]);
    }

    public function test_sanitise_project_definition_for_download_removes_terminal_end_jumps_in_forms_and_branches()
    {
        $projectDefinition = [
            'project' => [
                'small_description' => 'Test',
                'description' => 'Test',
                'forms' => [
                    [
                        'name' => 'Form 1',
                        'inputs' => [
                            [
                                'ref' => 'input_1',
                                'type' => 'text',
                                'jumps' => [
                                    [
                                        'to' => 'END',
                                        'when' => 'ALL',
                                    ],
                                ],
                                'branch' => [],
                                'group' => [],
                            ],
                            [
                                'ref' => 'branch_1',
                                'type' => config('epicollect.strings.inputs_type.branch'),
                                'jumps' => [
                                    [
                                        'to' => 'END',
                                        'when' => 'ALL',
                                    ],
                                ],
                                'branch' => [
                                    [
                                        'ref' => 'branch_input_1',
                                        'type' => 'text',
                                        'jumps' => [
                                            [
                                                'to' => 'END',
                                                'when' => 'ALL',
                                            ],
                                        ],
                                        'branch' => [],
                                        'group' => [],
                                    ],
                                    [
                                        'ref' => 'branch_input_2',
                                        'type' => 'text',
                                        'jumps' => [
                                            [
                                                'to' => 'END',
                                                'when' => 'ALL',
                                            ],
                                        ],
                                        'branch' => [],
                                        'group' => [],
                                    ],
                                ],
                                'group' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        $this->assertCount(1, $sanitised['project']['forms'][0]['inputs'][0]['jumps']);
        $this->assertSame([], $sanitised['project']['forms'][0]['inputs'][1]['jumps']);
        $this->assertCount(1, $sanitised['project']['forms'][0]['inputs'][1]['branch'][0]['jumps']);
        $this->assertSame([], $sanitised['project']['forms'][0]['inputs'][1]['branch'][1]['jumps']);
    }

    public function test_sanitise_project_definition_for_download_handles_small_description_padding()
    {
        $projectDefinition = [
            'project' => [
                'small_description' => 'Hi', // less than min, assume min is 5
                'description' => 'Test',
                'forms' => [],
            ],
        ];

        // Mock config if needed, but since it's config, assume it's set
        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        // Assuming min is 15, it should pad with 13 '_'
        $this->assertEquals('Hi' . str_repeat('_', 13), $sanitised['project']['small_description']);
    }

    public function test_sanitise_project_definition_for_download_sanitizes_decimal_min_max()
    {
        $projectDefinition = [
            'project' => [
                'small_description' => 'Test project',
                'description' => 'Test description',
                'forms' => [
                    [
                        'name' => 'Form 1',
                        'inputs' => [
                            [
                                'type' => config('epicollect.strings.inputs_type.decimal'),
                                'min' => '.5',
                                'max' => '-.78',
                            ],
                            [
                                'type' => config('epicollect.strings.inputs_type.decimal'),
                                'min' => '.9',
                                'max' => '1.23', // already correct
                            ],
                            [
                                'type' => 'text', // not decimal
                                'min' => '.4', // should not change
                            ],
                            [
                                'type' => 'group',
                                'group' => [
                                    [
                                        'type' => config('epicollect.strings.inputs_type.decimal'),
                                        'min' => '.2',
                                        'max' => '-.3',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'branch',
                                'branch' => [
                                    [
                                        'type' => config('epicollect.strings.inputs_type.decimal'),
                                        'min' => '.2',
                                        'max' => '-.3',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        // Check first decimal input
        $this->assertEquals('0.5', $sanitised['project']['forms'][0]['inputs'][0]['min']);
        $this->assertEquals('-0.78', $sanitised['project']['forms'][0]['inputs'][0]['max']);

        // Check second decimal input
        $this->assertEquals('0.9', $sanitised['project']['forms'][0]['inputs'][1]['min']);
        $this->assertEquals('1.23', $sanitised['project']['forms'][0]['inputs'][1]['max']);

        // Check non-decimal input, should not change
        $this->assertEquals('.4', $sanitised['project']['forms'][0]['inputs'][2]['min']);

        // Check nested decimal input in group
        $this->assertEquals('0.2', $sanitised['project']['forms'][0]['inputs'][3]['group'][0]['min']);
        $this->assertEquals('-0.3', $sanitised['project']['forms'][0]['inputs'][3]['group'][0]['max']);

        // Check nested decimal input in branch
        $this->assertEquals('0.2', $sanitised['project']['forms'][0]['inputs'][4]['branch'][0]['min']);
        $this->assertEquals('-0.3', $sanitised['project']['forms'][0]['inputs'][4]['branch'][0]['max']);
    }

    public function test_sanitise_project_definition_for_download_trims_and_normalizes_whitespace()
    {
        $rawSmall = "  \n This   is   small \r\n ";
        $rawDesc = "Line1\n\nLine2\r\n   More\t text ";

        $projectDefinition = [
            'project' => [
                'small_description' => $rawSmall,
                'description' => $rawDesc,
                'forms' => [],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        // Build expected small_description following the same steps as the service:
        // 1) trim, 2) pad to min length, 3) replace invalid chars, 4) collapse whitespace
        $min = config('epicollect.limits.project.small_desc.min');
        $trimSmall = trim($rawSmall);
        if (mb_strlen($trimSmall, 'UTF-8') < $min) {
            $padded = $trimSmall . str_repeat('_', $min - mb_strlen($trimSmall, 'UTF-8'));
        } else {
            $padded = $trimSmall;
        }
        $replaced = str_replace(['<', '>'], '_', $padded);
        $expectedSmall = preg_replace('/\s+/u', ' ', $replaced);

        // Expected description: trim + collapse whitespace
        $expectedDesc = preg_replace('/\s+/u', ' ', trim($rawDesc));

        $this->assertEquals($expectedSmall, $sanitised['project']['small_description']);
        $this->assertEquals($expectedDesc, $sanitised['project']['description']);
    }

    public function test_sanitise_project_definition_for_download_handles_newline_only_inputs()
    {
        $rawSmall = "\n";
        $rawDesc = "\r\n";

        $projectDefinition = [
            'project' => [
                'small_description' => $rawSmall,
                'description' => $rawDesc,
                'forms' => [],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        // After trim/collapse, these should become empty (then padded for small_description)
        $min = config('epicollect.limits.project.small_desc.min');
        $expectedSmall = str_repeat('_', $min);
        $expectedDesc = '';

        $this->assertEquals($expectedSmall, $sanitised['project']['small_description']);
        $this->assertEquals($expectedDesc, $sanitised['project']['description']);
    }

    public function test_sanitise_project_definition_for_download_replaces_angle_brackets()
    {
        $rawSmall = "Bad <small> name";
        $rawDesc = "Good > desc";

        $projectDefinition = [
            'project' => [
                'small_description' => $rawSmall,
                'description' => $rawDesc,
                'forms' => [],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        $this->assertStringNotContainsString('<', $sanitised['project']['small_description']);
        $this->assertStringNotContainsString('>', $sanitised['project']['small_description']);
        $this->assertStringContainsString('_', $sanitised['project']['small_description']);

        // Note: the service only replaces angle brackets in small_description
        // description is left unchanged
        $this->assertStringContainsString('>', $sanitised['project']['description']);
    }

    public function test_sanitise_project_definition_for_download_handles_very_long_strings()
    {
        $long = str_repeat('あ', 5000); // multibyte Japanese char repeated

        $projectDefinition = [
            'project' => [
                'small_description' => $long,
                'description' => $long,
                'forms' => [],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForExport($projectDefinition);

        // small_description should be preserved (no truncation here), but ensure length matches
        $this->assertEquals(mb_strlen($long, 'UTF-8'), mb_strlen($sanitised['project']['small_description'], 'UTF-8'));
        $this->assertEquals(mb_strlen($long, 'UTF-8'), mb_strlen($sanitised['project']['description'], 'UTF-8'));
    }
}
