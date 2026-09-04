<?php

namespace Tests\Http\Validation\Project\Mapping;

use ec5\Http\Validation\Project\Mapping\RuleMappingDelete;
use Tests\TestCase;

class RuleMappingDeleteTest extends TestCase
{
    protected RuleMappingDelete $ruleMappingCreate;

    public function setUp(): void
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
