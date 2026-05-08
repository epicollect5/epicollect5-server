<?php

namespace Tests\Http\Validation\Project\Mapping;

use ec5\Http\Validation\Project\Mapping\RuleMappingInput;
use ec5\Http\Validation\Project\Mapping\RuleMappingPossibleAnswer;
use ec5\Http\Validation\Project\Mapping\RuleMappingStructure;
use Faker\Factory as Faker;
use Faker\Generator;
use Tests\TestCase;

class RuleMappingStructureTest extends TestCase
{
    protected RuleMappingStructure $ruleMappingStructure;
    private Generator $faker;

    public function setUp(): void
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
                'name' => 'Map ' . $this->faker->unique()->regexify('^[A-Za-z0-9 \-\_]{3,10}$'),
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

    /**
     * Missing is_default must map to required field error.
     *
     * @return void
     */
    public function testMissingIsDefaultFailsValidation()
    {
        $data = [
            'name' => 'Map test',
            'forms' => [
                ['fakeRef']
            ],
            'map_index' => rand(1, 100)
        ];

        $this->ruleMappingStructure->validate($data);

        $this->assertTrue($this->ruleMappingStructure->hasErrors());
        $expectedErrors = [
            'is_default' => ['ec5_21']
        ];
        $this->assertSame($expectedErrors, $this->ruleMappingStructure->errors());
    }
}
