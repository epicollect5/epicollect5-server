<?php

namespace Tests\Http\Validation\Project\Mapping;

use ec5\DTO\ProjectMappingDTO;
use ec5\Http\Validation\Project\Mapping\RuleMappingUpdate;
use Faker\Factory as Faker;
use Faker\Generator;
use Tests\TestCase;

class RuleMappingUpdateTest extends TestCase
{
    protected RuleMappingUpdate $ruleMappingUpdate;
    private Generator $faker;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->ruleMappingUpdate = new RuleMappingUpdate();
    }

    public function test_valid_payload()
    {
        $count = rand(25, 50);
        for ($i = 0; $i < $count; $i++) {
            $actions = ['make-default', 'rename', 'update'];
            $randomAction = $actions[array_rand($actions)];
            $payload = [
                'action' => $randomAction,
                'map_index' => rand(1, 100)
            ];
            switch ($randomAction) {
                case 'make-default':
                    $payload['is_default'] = (bool)rand(0, 1);
                    break;
                case 'rename':
                    $payload['name'] = 'map_01';
                    break;
                case 'update':
                    $payload['mapping'] = ['aFormRef'];//need at least an element, not to be empty
                    break;
            }
            $this->ruleMappingUpdate->validate($payload);
            if (sizeof($this->ruleMappingUpdate->errors()) > 0) {
                echo print_r($this->ruleMappingUpdate->errors(), true);
            }
            $this->assertFalse($this->ruleMappingUpdate->hasErrors());
            $this->ruleMappingUpdate->resetErrors();
        }
    }

    public function test_invalid_action_payload()
    {
        //action wrong
        $count = rand(25, 50);
        for ($i = 0; $i < $count; $i++) {
            $actions = ['one', 'two', 'three'];
            $randomAction = $actions[array_rand($actions)];
            $payload = [
                'action' => $randomAction,
                'map_index' => rand(1, 100)
            ];
            switch ($randomAction) {
                case 'make-default':
                    $payload['is_default'] = (bool)rand(0, 1);
                    break;
                case 'rename':
                    $payload['name'] = $this->faker->regexify('^[A-Za-z0-9 \-\_]{3,10}$');
                    break;
                case 'update':
                    $payload['mapping'] = ['aFormRef'];//need at least an element, not to be empty
                    break;
            }
            $this->ruleMappingUpdate->validate($payload);
            $this->assertTrue($this->ruleMappingUpdate->hasErrors());
            $this->ruleMappingUpdate->resetErrors();
        }
    }

    public function test_invalid__map_index_in_payload()
    {
        $count = rand(25, 50);
        for ($i = 0; $i < $count; $i++) {
            $actions = ['make-default', 'rename', 'update'];
            $randomAction = $actions[array_rand($actions)];
            $payload = [
                'action' => $randomAction,
                'map_index' => $this->faker->randomElement(['a', 'b', 'c', 'd', 'e'])
            ];
            switch ($randomAction) {
                case 'make-default':
                    $payload['is_default'] = (bool)rand(0, 1);
                    break;
                case 'rename':
                    $payload['name'] = 'Valid Name';
                    break;
                case 'update':
                    $payload['mapping'] = ['aFormRef'];//need at least an element, not to be empty
                    break;
            }
            $this->ruleMappingUpdate->validate($payload);
            if (sizeof($this->ruleMappingUpdate->errors()) > 0) {
                //echo print_r($this->ruleMappingUpdate->errors(), true);
            }
            $this->assertTrue($this->ruleMappingUpdate->hasErrors());
            $this->ruleMappingUpdate->resetErrors();
        }
    }

    public function test_missing_name_in_rename_payload()
    {
        $count = rand(25, 50);
        for ($i = 0; $i < $count; $i++) {
            $actions = ['rename'];
            $randomAction = $actions[array_rand($actions)];
            $payload = [
                'action' => $randomAction,
                'map_index' => rand(1, 100)
            ];
            switch ($randomAction) {
                case 'make-default':
                    $payload['is_default'] = (bool)rand(0, 1);
                    break;
                case 'rename':
                    $payload['name'] = null;
                    break;
                case 'update':
                    $payload['mapping'] = ['aFormRef'];//need at least an element, not to be empty
                    break;
            }
            $this->ruleMappingUpdate->validate($payload);
            if (sizeof($this->ruleMappingUpdate->errors()) > 0) {
                // echo print_r($this->ruleMappingUpdate->errors(), true);
            }
            $this->assertTrue($this->ruleMappingUpdate->hasErrors());
            $this->ruleMappingUpdate->resetErrors();
        }
    }

    public function test_missing_mapping_in_update_payload()
    {
        $count = rand(25, 50);
        for ($i = 0; $i < $count; $i++) {
            $actions = ['update'];
            $randomAction = $actions[array_rand($actions)];
            $payload = [
                'action' => $randomAction,
                'map_index' => rand(1, 100)
            ];
            switch ($randomAction) {
                case 'make-default':
                    $payload['is_default'] = (bool)rand(0, 1);
                    break;
                case 'rename':
                    $payload['name'] = $this->faker->regexify('^[A-Za-z0-9 \-\_]{3,10}$');
                    break;
                case 'update':
                    $payload['mapping'] = null;
                    break;
            }
            $this->ruleMappingUpdate->validate($payload);
            $this->assertTrue($this->ruleMappingUpdate->hasErrors());
            $this->ruleMappingUpdate->resetErrors();
        }
    }

    /**
     * Regression: form-encoded POSTs deliver map_index as a string.
     * additionalChecks must still block rename/update of the default
     * EC5_AUTO mapping (map_index 0) regardless of string vs int.
     */
    public function test_rename_default_mapping_is_blocked_when_map_index_is_string_zero()
    {
        $projectMapping = $this->makeProjectMapping();
        $this->ruleMappingUpdate->validate([
            'action' => 'rename',
            'map_index' => '0',
            'name' => 'New Name'
        ]);
        $this->assertFalse(
            $this->ruleMappingUpdate->hasErrors(),
            'Validation rule layer should accept string "0" but additionalChecks must still fire.'
        );
        $this->ruleMappingUpdate->additionalChecks($projectMapping, [
            'action' => 'rename',
            'map_index' => '0',
            'name' => 'New Name'
        ]);
        $this->assertTrue($this->ruleMappingUpdate->hasErrors());
        $this->assertArrayHasKey('mapping', $this->ruleMappingUpdate->errors());
        $this->assertEquals(['ec5_91'], $this->ruleMappingUpdate->errors()['mapping']);
    }

    public function test_update_default_mapping_is_blocked_when_map_index_is_string_zero()
    {
        $projectMapping = $this->makeProjectMapping();
        $this->ruleMappingUpdate->validate([
            'action' => 'update',
            'map_index' => '0',
            'mapping' => ['aFormRef']
        ]);
        $this->assertFalse($this->ruleMappingUpdate->hasErrors());
        $this->ruleMappingUpdate->additionalChecks($projectMapping, [
            'action' => 'update',
            'map_index' => '0',
            'mapping' => ['aFormRef']
        ]);
        $this->assertTrue($this->ruleMappingUpdate->hasErrors());
        $this->assertArrayHasKey('mapping', $this->ruleMappingUpdate->errors());
        $this->assertEquals(['ec5_91'], $this->ruleMappingUpdate->errors()['mapping']);
    }

    public function test_rename_default_mapping_is_blocked_when_map_index_is_int_zero()
    {
        $projectMapping = $this->makeProjectMapping();
        $this->ruleMappingUpdate->validate([
            'action' => 'rename',
            'map_index' => 0,
            'name' => 'New Name'
        ]);
        $this->ruleMappingUpdate->additionalChecks($projectMapping, [
            'action' => 'rename',
            'map_index' => 0,
            'name' => 'New Name'
        ]);
        $this->assertTrue($this->ruleMappingUpdate->hasErrors());
        $this->assertEquals(['ec5_91'], $this->ruleMappingUpdate->errors()['mapping']);
    }

    private function makeProjectMapping(): ProjectMappingDTO
    {
        $dto = new ProjectMappingDTO();
        $dto->setData([
            0 => [
                'name' => config('epicollect.mappings.default_mapping_name'),
                'is_default' => true,
                'map_index' => 0,
                'forms' => []
            ],
            1 => [
                'name' => 'Custom Map',
                'is_default' => false,
                'map_index' => 1,
                'forms' => []
            ]
        ]);
        return $dto;
    }
}
