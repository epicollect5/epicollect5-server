<?php

namespace Tests\Http\Validation\Project\Mapping;

use Faker\Factory as Faker;
use Tests\TestCase;
use ec5\Http\Validation\Project\Mapping\RuleMappingUpdate;

class RuleMappingUpdateTest extends TestCase
{
    protected $ruleMappingUpdate;
    private $faker;

    public function setUp()
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
            };
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
            };
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
            };
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
            };
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
            };
            $this->ruleMappingUpdate->validate($payload);
            $this->assertTrue($this->ruleMappingUpdate->hasErrors());
            $this->ruleMappingUpdate->resetErrors();
        }
    }
}