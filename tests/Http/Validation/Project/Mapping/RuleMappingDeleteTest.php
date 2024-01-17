<?php

namespace Tests\Http\Validation\Project\Mapping;

use Faker\Factory as Faker;
use Tests\TestCase;
use ec5\Http\Validation\Project\Mapping\RuleMappingDelete;

class RuleMappingDeleteTest extends TestCase
{
    protected $ruleMappingCreate;

    public function setUp()
    {
        parent::setUp();
        $this->ruleMappingDelete = new RuleMappingDelete();
    }

    public function test_valid_index()
    {
        $count = rand(1, 50);
        for ($i = 1; $i < $count; $i++) {
            $data = [
                'map_index' => $i
            ];
            $this->ruleMappingDelete->validate($data);
            $this->assertFalse($this->ruleMappingDelete->hasErrors());
            $this->ruleMappingDelete->resetErrors();
        }
    }

    public function test_invalid_index()
    {
        $data = [
            'map_index' => 0
        ];
        $this->ruleMappingDelete->validate($data);
        $this->assertTrue($this->ruleMappingDelete->hasErrors());
        $this->ruleMappingDelete->resetErrors();
    }
}