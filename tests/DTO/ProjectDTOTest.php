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
        $this->assertSame($expectedSmallDescription, $sanitised['project']['small_description']);
        $this->assertSame("Form\tName", $raw['project']['forms'][0]['name']);
        $this->assertSame('Form Name', $sanitised['project']['forms'][0]['name']);
        $this->assertSame('.5', $raw['project']['forms'][0]['inputs'][0]['min']);
        $this->assertSame('0.5', $sanitised['project']['forms'][0]['inputs'][0]['min']);
    }
}
