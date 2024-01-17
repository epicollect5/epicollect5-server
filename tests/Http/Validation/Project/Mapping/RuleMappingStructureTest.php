<?php

namespace Tests\Http\Validation\Project\Mapping;

use ec5\Http\Validation\Project\Mapping\RuleMappingInput;
use ec5\Http\Validation\Project\Mapping\RuleMappingPossibleAnswer;
use Faker\Factory as Faker;
use Tests\TestCase;
use ec5\Http\Validation\Project\Mapping\RuleMappingStructure;

class RuleMappingStructureTest extends TestCase
{
    protected $ruleMappingStructure;
    private $faker;

    public function setUp()
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->ruleMappingStructure = new RuleMappingStructure(
            new RuleMappingInput(),
            new RuleMappingPossibleAnswer()
        );
    }

    public function test_valid_structure()
    {
        $count = rand(1, 50);
        for ($i = 1; $i < $count; $i++) {
            $data = [
                'name' => $this->faker->unique()->regexify('^[A-Za-z0-9 \-\_]{3,20}$'),
                'forms' => [
                    ['fakeRef']
                ],
                'is_default' => (bool)rand(0, 1),
                'map_index' => rand(1, 100)
            ];
            $this->ruleMappingStructure->validate($data);
            $this->assertFalse($this->ruleMappingStructure->hasErrors());
            $this->ruleMappingStructure->resetErrors();
        }
    }
}