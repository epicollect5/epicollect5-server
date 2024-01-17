<?php

namespace Tests\Http\Validation\Project\Mapping;

use Faker\Factory as Faker;
use Tests\TestCase;
use ec5\Http\Validation\Project\Mapping\RuleMappingInput;

class RuleMappingInputTest extends TestCase
{
    protected $ruleMappingInput;
    private $faker;


    public function setUp()
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->ruleMappingInput = new RuleMappingInput();
    }

    public function test_valid_input()
    {
        $count = rand(10, 50);
        for ($i = 1; $i < $count; $i++) {
            $data = [
                'hide' => (bool)rand(0, 1),
                'group' => [],
                'branch' => [],
                'map_to' => $this->faker->regexify('^[A-Za-z0-9\_]{1,20}$'),
                'possible_answers' => []
            ];
            $this->ruleMappingInput->validate($data);
            $this->assertFalse($this->ruleMappingInput->hasErrors());
            $this->ruleMappingInput->resetErrors();
        }
    }

    public function test_invalid_input()
    {
        $count = rand(1, 5);
        for ($i = 1; $i < $count; $i++) {
            $data = [
                'hide' => (bool)rand(0, 1),
                'group' => [],
                'branch' => [],
                'map_to' => '',
                'possible_answers' => []
            ];
            $this->ruleMappingInput->validate($data);
            $this->assertTrue($this->ruleMappingInput->hasErrors());
            $this->ruleMappingInput->resetErrors();
        }

        $count = rand(1, 5);
        for ($i = 1; $i < $count; $i++) {
            $data = [
                'hide' => (bool)rand(0, 1),
                'group' => [],
                'branch' => [],
                'map_to' => $this->faker->regexify('^[A-Za-z0-9\-]{21,30}$'),
                'possible_answers' => []
            ];
            $this->ruleMappingInput->validate($data);
            $this->assertTrue($this->ruleMappingInput->hasErrors());
            $this->ruleMappingInput->resetErrors();
        }

        $count = rand(1, 5);
        for ($i = 1; $i < $count; $i++) {
            $data = [
                'hide' => (bool)rand(0, 1),
                'group' => [],
                'branch' => [],
                'map_to' => $this->faker->regexify('^[*$£@!-#?&^%.,;]{1,20}$'),
                'possible_answers' => []
            ];
            $this->ruleMappingInput->validate($data);
            $this->assertTrue($this->ruleMappingInput->hasErrors());
            $this->ruleMappingInput->resetErrors();
        }

        $data = [
            'hide' => (bool)rand(0, 1),
            'group' => null,
            'branch' => [],
            'map_to' => $this->faker->regexify('^[*$£@!-#?&^%.,;]{1,20}$'),
            'possible_answers' => []
        ];
        $this->ruleMappingInput->validate($data);
        $this->assertTrue($this->ruleMappingInput->hasErrors());
        $this->ruleMappingInput->resetErrors();

        $data = [
            'hide' => null,
            'group' => [],
            'branch' => [],
            'map_to' => $this->faker->regexify('^[*$£@!-#?&^%.,;]{1,20}$'),
            'possible_answers' => []
        ];
        $this->ruleMappingInput->validate($data);
        $this->assertTrue($this->ruleMappingInput->hasErrors());
        $this->ruleMappingInput->resetErrors();

        $data = [
            'hide' => (bool)rand(0, 1),
            'group' => [],
            'branch' => null,
            'map_to' => $this->faker->regexify('^[*$£@!-#?&^%.,;]{1,20}$'),
            'possible_answers' => []
        ];
        $this->ruleMappingInput->validate($data);
        $this->assertTrue($this->ruleMappingInput->hasErrors());
        $this->ruleMappingInput->resetErrors();

        $data = [
            'hide' => (bool)rand(0, 1),
            'group' => [],
            'branch' => [],
            'map_to' => $this->faker->regexify('^[*$£@!-#?&^%.,;]{1,20}$'),
            'possible_answers' => null
        ];
        $this->ruleMappingInput->validate($data);
        $this->assertTrue($this->ruleMappingInput->hasErrors());
        $this->ruleMappingInput->resetErrors();

        $data = [
            'hide' => (bool)rand(0, 1),
            'group' => [],
            'branch' => [],
            'map_to' => [],
            'possible_answers' => []
        ];
        $this->ruleMappingInput->validate($data);
        $this->assertTrue($this->ruleMappingInput->hasErrors());
        $this->ruleMappingInput->resetErrors();
    }
}