<?php

namespace Tests\DTO;

use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Services\Mapping\ProjectMappingService;
use Tests\TestCase;

class ProjectDTOTest extends TestCase
{
    public function test_get_sanitised_project_definition_returns_sanitised_copy_without_mutating_raw_definition()
    {
        $project = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );

        $project->addProjectDefinition([
            'project' => [
                'small_description' => "  A<\n",
                'description' => "Desc\n",
                'forms' => [
                    [
                        'name' => "Form\tName",
                        'inputs' => [
                            [
                                'type' => config('epicollect.strings.inputs_type.decimal'),
                                'min' => '.5',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $sanitised = $project->getSanitisedProjectDefinition();
        $raw = $project->getProjectDefinition()->getData();
        $minimumLength = max(
            config('epicollect.limits.project.small_desc.min'),
            config('epicollect.limits.project.form.name.min')
        );
        $expectedSmallDescription = 'A_' . str_repeat('_', $minimumLength - 2);

        $this->assertNotSame($raw, $sanitised);
        $this->assertSame("  A<\n", $raw['project']['small_description']);
        $this->assertArrayNotHasKey('logo_url', $sanitised['project']);
        $this->assertSame($expectedSmallDescription, $sanitised['project']['small_description']);
        $this->assertSame("Form\tName", $raw['project']['forms'][0]['name']);
        $this->assertSame('Form Name', $sanitised['project']['forms'][0]['name']);
        $this->assertSame('.5', $raw['project']['forms'][0]['inputs'][0]['min']);
        $this->assertSame('0.5', $sanitised['project']['forms'][0]['inputs'][0]['min']);
    }

    public function test_sanitise_project_definition_for_export_pads_and_cleans_without_double_padding()
    {
        $definition = [
            'project' => [
                'small_description' => "  A<\n",
                'description' => "Desc\n",
                'forms' => [
                    [
                        'name' => "Form\tName",
                        'inputs' => [
                            [
                                'type' => config('epicollect.strings.inputs_type.decimal'),
                                'min' => '.5',
                            ],
                            [
                                'type' => config('epicollect.strings.inputs_type.branch'),
                                'group' => [['ref' => 'g1']],
                                'branch' => [],
                                'jumps' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitised = ProjectDTO::sanitiseProjectDefinitionForExport($definition);

        // logo_url is always stripped from the returned definition
        $this->assertArrayNotHasKey('logo_url', $sanitised['project']);

        // small_description: trimmed, '<' replaced with '_', whitespace collapsed,
        // and padded to small_desc.min ONLY (the form.name.min block must not re-pad)
        $minSmallDesc = (int)config('epicollect.limits.project.small_desc.min');
        $this->assertSame($minSmallDesc, mb_strlen($sanitised['project']['small_description']));
        $this->assertSame('A_' . str_repeat('_', $minSmallDesc - 2), $sanitised['project']['small_description']);

        // form name whitespace is collapsed
        $this->assertSame('Form Name', $sanitised['project']['forms'][0]['name']);

        // decimal leading zero is fixed
        $this->assertSame('0.5', $sanitised['project']['forms'][0]['inputs'][0]['min']);

        // a branch input's group is forced to an empty array
        $this->assertSame([], $sanitised['project']['forms'][0]['inputs'][1]['group']);
    }

    public function test_sanitise_collapses_whitespace_only_description_to_empty(): void
    {
        $base = ['project' => [
            'small_description' => 'Valid small description here',
            'description' => '',
            'forms' => [],
        ]];

        // whitespace-only description normalises to the schema-legal empty string
        $crlf = $base;
        $crlf['project']['description'] = "\r\n";
        $this->assertSame('', ProjectDTO::sanitiseProjectDefinitionForExport($crlf)['project']['description']);

        // a single collapsed space (collapseWhitespace output) also normalises to empty
        $space = $base;
        $space['project']['description'] = ' ';
        $this->assertSame('', ProjectDTO::sanitiseProjectDefinitionForExport($space)['project']['description']);

        // genuine inner newlines are preserved (and valid), NOT emptied
        $inner = $base;
        $inner['project']['description'] = "Line A\r\nLine B";
        $this->assertSame('Line A Line B', ProjectDTO::sanitiseProjectDefinitionForExport($inner)['project']['description']);

        // small_description padding behaviour is untouched
        $short = ['project' => [
            'small_description' => 'short',
            'description' => 'ok',
            'forms' => [],
        ]];
        $min = (int) config('epicollect.limits.project.small_desc.min');
        $this->assertSame($min, mb_strlen(ProjectDTO::sanitiseProjectDefinitionForExport($short)['project']['small_description']));
    }

    public function test_add_project_definition_sanitises_terminal_end_jumps_in_forms_and_branches()
    {
        $project = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );

        $project->addProjectDefinition([
            'project' => [
                'forms' => [
                    [
                        'inputs' => [
                            [
                                'ref' => 'input_1',
                                'type' => 'text',
                                'jumps' => [
                                    ['to' => 'END', 'when' => 'ALL'],
                                ],
                                'branch' => [],
                                'group' => [],
                            ],
                            [
                                'ref' => 'branch_1',
                                'type' => config('epicollect.strings.inputs_type.branch'),
                                'jumps' => [
                                    ['to' => 'END', 'when' => 'ALL'],
                                ],
                                'branch' => [
                                    [
                                        'ref' => 'branch_input_1',
                                        'type' => 'text',
                                        'jumps' => [
                                            ['to' => 'END', 'when' => 'ALL'],
                                        ],
                                        'branch' => [],
                                        'group' => [],
                                    ],
                                    [
                                        'ref' => 'branch_input_2',
                                        'type' => 'text',
                                        'jumps' => [
                                            ['to' => 'END', 'when' => 'ALL'],
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
        ]);

        $sanitised = $project->getProjectDefinition()->getData();

        $this->assertCount(1, $sanitised['project']['forms'][0]['inputs'][0]['jumps']);
        $this->assertSame('END', $sanitised['project']['forms'][0]['inputs'][0]['jumps'][0]['to']);
        $this->assertSame([], $sanitised['project']['forms'][0]['inputs'][1]['jumps']);
        $this->assertCount(1, $sanitised['project']['forms'][0]['inputs'][1]['branch'][0]['jumps']);
        $this->assertSame([], $sanitised['project']['forms'][0]['inputs'][1]['branch'][1]['jumps']);
    }
}
