<?php

namespace Tests\DTO;

use ec5\DTO\ProjectMappingDTO;
use Tests\TestCase;

class ProjectMappingDTOTest extends TestCase
{
    public function test_init_preserves_a_single_valid_default_mapping(): void
    {
        $projectMapping = new ProjectMappingDTO();

        $projectMapping->init([
            0 => [
                'name' => 'EC5 AUTO',
                'is_default' => false,
                'forms' => [],
                'map_index' => 0
            ],
            1 => [
                'name' => 'Custom',
                'is_default' => true,
                'forms' => [],
                'map_index' => 1
            ]
        ]);

        $mappingData = $projectMapping->getData();

        $this->assertFalse($mappingData[0]['is_default']);
        $this->assertTrue($mappingData[1]['is_default']);
    }

    public function test_init_prefers_custom_default_when_multiple_defaults_exist(): void
    {
        $projectMapping = new ProjectMappingDTO();

        $projectMapping->init([
            0 => [
                'name' => 'EC5 AUTO',
                'is_default' => true,
                'forms' => [],
                'map_index' => 0
            ],
            1 => [
                'name' => 'Custom',
                'is_default' => true,
                'forms' => [],
                'map_index' => 1
            ]
        ]);

        $mappingData = $projectMapping->getData();

        $this->assertFalse($mappingData[0]['is_default']);
        $this->assertTrue($mappingData[1]['is_default']);
    }

    public function test_init_falls_back_to_ec5_auto_when_no_default_exists(): void
    {
        $projectMapping = new ProjectMappingDTO();

        $projectMapping->init([
            0 => [
                'name' => 'EC5 AUTO',
                'is_default' => false,
                'forms' => [],
                'map_index' => 0
            ],
            1 => [
                'name' => 'Custom',
                'is_default' => false,
                'forms' => [],
                'map_index' => 1
            ]
        ]);

        $mappingData = $projectMapping->getData();

        $this->assertTrue($mappingData[0]['is_default']);
        $this->assertFalse($mappingData[1]['is_default']);
    }

    public function test_init_treats_missing_is_default_as_false(): void
    {
        $projectMapping = new ProjectMappingDTO();

        $projectMapping->init([
            0 => [
                'name' => 'EC5 AUTO',
                'forms' => [],
                'map_index' => 0
            ],
            1 => [
                'name' => 'Custom',
                'is_default' => true,
                'forms' => [],
                'map_index' => 1
            ]
        ]);

        $mappingData = $projectMapping->getData();

        $this->assertFalse($mappingData[0]['is_default']);
        $this->assertTrue($mappingData[1]['is_default']);
    }

    public function test_init_normalizes_json_loaded_from_the_database(): void
    {
        $projectMapping = new ProjectMappingDTO();

        $projectMapping->init(json_encode([
            0 => [
                'name' => 'EC5 AUTO',
                'is_default' => true,
                'forms' => [],
                'map_index' => 0
            ],
            1 => [
                'name' => 'Custom A',
                'is_default' => true,
                'forms' => [],
                'map_index' => 1
            ],
            2 => [
                'name' => 'Custom B',
                'is_default' => true,
                'forms' => [],
                'map_index' => 2
            ]
        ]));

        $mappingData = $projectMapping->getData();

        $this->assertFalse($mappingData[0]['is_default']);
        $this->assertTrue($mappingData[1]['is_default']);
        $this->assertFalse($mappingData[2]['is_default']);
    }
}
