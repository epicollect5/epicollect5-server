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
        $count = rand(2, 50);
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

    /**
     * Missing map_index must map to required field error.
     *
     * @return void
     */
    public function testMissingMapIndexFailsValidation()
    {
        $data = [
            'name' => 'Map test',
            'forms' => [
                ['fakeRef']
            ],
            'is_default' => true
        ];

        $this->ruleMappingStructure->validate($data);

        $this->assertTrue($this->ruleMappingStructure->hasErrors());
        $expectedErrors = [
            'map_index' => ['ec5_21']
        ];
        $this->assertSame($expectedErrors, $this->ruleMappingStructure->errors());
    }

    /**
     * Both is_default and map_index missing must report both errors.
     *
     * @return void
     */
    public function testMissingBothRequiredFieldsFailsValidation()
    {
        $data = [
            'name' => 'Map test',
            'forms' => [
                ['fakeRef']
            ]
        ];

        $this->ruleMappingStructure->validate($data);

        $this->assertTrue($this->ruleMappingStructure->hasErrors());
        $errors = $this->ruleMappingStructure->errors();
        $this->assertArrayHasKey('is_default', $errors);
        $this->assertArrayHasKey('map_index', $errors);
        $this->assertSame(['ec5_21'], $errors['is_default']);
        $this->assertSame(['ec5_21'], $errors['map_index']);
    }

    /**
     * Non-boolean is_default must fail validation.
     *
     * @return void
     */
    public function testNonBooleanIsDefaultFailsValidation()
    {
        $data = [
            'name' => 'Map test',
            'forms' => [
                ['fakeRef']
            ],
            'is_default' => 'yes',
            'map_index' => 1
        ];

        $this->ruleMappingStructure->validate($data);

        $this->assertTrue($this->ruleMappingStructure->hasErrors());
        $errors = $this->ruleMappingStructure->errors();
        $this->assertArrayHasKey('is_default', $errors);
    }

    /**
     * Non-integer map_index must fail validation.
     *
     * @return void
     */
    public function testNonIntegerMapIndexFailsValidation()
    {
        $data = [
            'name' => 'Map test',
            'forms' => [
                ['fakeRef']
            ],
            'is_default' => true,
            'map_index' => 'one'
        ];

        $this->ruleMappingStructure->validate($data);

        $this->assertTrue($this->ruleMappingStructure->hasErrors());
        $errors = $this->ruleMappingStructure->errors();
        $this->assertArrayHasKey('map_index', $errors);
    }
}
