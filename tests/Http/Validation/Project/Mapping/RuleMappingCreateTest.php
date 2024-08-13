<?php

namespace Tests\Http\Validation\Project\Mapping;

use Faker\Factory as Faker;
use Tests\TestCase;
use ec5\Http\Validation\Project\Mapping\RuleMappingCreate;

class RuleMappingCreateTest extends TestCase
{
    protected $ruleMappingCreate;
    private $faker;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->ruleMappingCreate = new RuleMappingCreate();
    }

    public function test_valid_names()
    {
        $count = rand(1, 500);
        for ($i = 0; $i < $count; $i++) {
            $data = [
                'name' => 'Map ' . $this->faker->regexify('^[A-Za-z0-9 \-\_]{3,10}$')
            ];
            $this->ruleMappingCreate->validate($data);
            $this->assertFalse($this->ruleMappingCreate->hasErrors());
            $this->ruleMappingCreate->resetErrors();
        }
    }

    public function test_invalid_names()
    {
        $count = rand(1, 50);
        for ($i = 0; $i < $count; $i++) {
            $data = [
                'name' => $this->faker->regexify('^[A-Za-z0-9 \-\_]{0,2}$')
            ];
            $this->ruleMappingCreate->validate($data);
            $this->assertTrue($this->ruleMappingCreate->hasErrors());
            $this->ruleMappingCreate->resetErrors();
        }

        $count = rand(1, 50);
        for ($i = 0; $i < $count; $i++) {

            //we need this to make sure the length is correct as regexify() fails sometimes
            do {
                $invalidName = $this->faker->regexify('^[A-Za-z0-9 \-\_]{21,50}$');
            } while (strlen($invalidName) < 21 || strlen($invalidName) > 50);

            $data = [
                'name' => $invalidName
            ];
            $this->ruleMappingCreate->validate($data);
            $this->assertTrue($this->ruleMappingCreate->hasErrors(), print_r($data['name'], true));
            $this->ruleMappingCreate->resetErrors();
        }

        $invalidStrings = [
            "@InvalidString",
            "TooLongString1234567890123456",
            "Spaces Are Not Allowed",
            "SpecialChar!",
            "Sh",
            "?AtStart",
            "Invalid_String$"
        ];


        foreach ($invalidStrings as $invalidString) {
            $data = [
                'name' => $invalidString
            ];
            $this->ruleMappingCreate->validate($data);
            $this->assertTrue($this->ruleMappingCreate->hasErrors());
            $this->ruleMappingCreate->resetErrors();
        }
    }
}