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
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForDownload($projectDefinition);

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

        $sanitised = $this->projectService->sanitiseProjectDefinitionForDownload($projectDefinition);

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

        $sanitised = $this->projectService->sanitiseProjectDefinitionForDownload($projectDefinition);

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
                                'type' => 'text',
                                'jumps' => [
                                    [
                                        'to' => 'END',
                                        'when' => 'ALL',
                                        'has_valid_destination' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForDownload($projectDefinition);

        $this->assertArrayNotHasKey('has_valid_destination', $sanitised['project']['forms'][0]['inputs'][0]['jumps'][0]);
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
        $sanitised = $this->projectService->sanitiseProjectDefinitionForDownload($projectDefinition);

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
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = $this->projectService->sanitiseProjectDefinitionForDownload($projectDefinition);

        // Check first decimal input
        $this->assertEquals('0.5', $sanitised['project']['forms'][0]['inputs'][0]['min']);
        $this->assertEquals('-0.78', $sanitised['project']['forms'][0]['inputs'][0]['max']);

        // Check second decimal input
        $this->assertEquals('0.9', $sanitised['project']['forms'][0]['inputs'][1]['min']);
        $this->assertEquals('1.23', $sanitised['project']['forms'][0]['inputs'][1]['max']);

        // Check non-decimal input, should not change
        $this->assertEquals('.4', $sanitised['project']['forms'][0]['inputs'][2]['min']);
    }
}
