<?php

namespace Tests\Http\Validation\Project\Mapping;

use Exception;
use Faker\Factory as Faker;
use Tests\TestCase;
use ec5\Http\Validation\Project\Mapping\RuleMappingPossibleAnswer;

class RuleMappingPossibleAnswerTest extends TestCase
{
    protected $rulePossibleAnswer;
    private $faker;


    public function setUp()
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->rulePossibleAnswer = new RuleMappingPossibleAnswer();
    }

    public function test_valid_possible_answer_map_to()
    {
        $rules = [
            'map_to' => 'required|string|max:150|regex:/((?![<>]).)$/',
        ];

        $count = rand(25, 50);
        for ($i = 1; $i < $count; $i++) {
            $mapTo = $this->faker->regexify('^[A-Za-z0-9\-]{1,150}$');
            $data = [
                'map_to' => $mapTo,
            ];
            $this->rulePossibleAnswer->validate($data);
            if (sizeof($this->rulePossibleAnswer->errors()) > 0) {
                echo print_r($this->rulePossibleAnswer->errors(), true);
                echo $mapTo;
            }
            $this->assertFalse($this->rulePossibleAnswer->hasErrors());
            $this->rulePossibleAnswer->resetErrors();
        }
    }

    public function test_invalid_possible_answer_map_to()
    {
        $count = rand(25, 50);
        for ($i = 1; $i < $count; $i++) {
            $mapTo = $this->faker->regexify('^[A-Za-z0-9\-]$');
            $mapTo = '<' . $mapTo . '>';
            $data = [
                'map_to' => $mapTo,
            ];
            $this->rulePossibleAnswer->validate($data);
            $this->assertTrue($this->rulePossibleAnswer->hasErrors());
            $this->rulePossibleAnswer->resetErrors();
        }

        for ($i = 1; $i < $count; $i++) {
            $mapTo = $this->generateRandomString(rand(151, 300));
            $data = [
                'map_to' => $mapTo,
            ];
            $this->rulePossibleAnswer->validate($data);
            if (sizeof($this->rulePossibleAnswer->errors()) === 0) {
                echo $mapTo;
            }
            $this->assertTrue($this->rulePossibleAnswer->hasErrors());
            $this->rulePossibleAnswer->resetErrors();
        }

        $mapTo = [];
        $data = [
            'map_to' => $mapTo,
        ];
        $this->rulePossibleAnswer->validate($data);
        $this->assertTrue($this->rulePossibleAnswer->hasErrors());
        $this->rulePossibleAnswer->resetErrors();

        $mapTo = true;
        $data = [
            'map_to' => $mapTo,
        ];
        $this->rulePossibleAnswer->validate($data);
        $this->assertTrue($this->rulePossibleAnswer->hasErrors());
        $this->rulePossibleAnswer->resetErrors();

        $mapTo = false;
        $data = [
            'map_to' => $mapTo,
        ];
        $this->rulePossibleAnswer->validate($data);
        $this->assertTrue($this->rulePossibleAnswer->hasErrors());
        $this->rulePossibleAnswer->resetErrors();

        $mapTo = null;
        $data = [
            'map_to' => $mapTo,
        ];
        $this->rulePossibleAnswer->validate($data);
        $this->assertTrue($this->rulePossibleAnswer->hasErrors());
        $this->rulePossibleAnswer->resetErrors();

        $mapTo = rand(0, 100);
        $data = [
            'map_to' => $mapTo,
        ];
        $this->rulePossibleAnswer->validate($data);
        $this->assertTrue($this->rulePossibleAnswer->hasErrors());
        $this->rulePossibleAnswer->resetErrors();
    }

    /**
     * @throws Exception
     */
    private function generateRandomString($length): string
    {
        return bin2hex(random_bytes(($length + 1) / 2));
    }
}