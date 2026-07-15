<?php

namespace Tests\Http\Validation\Project\Mapping;

use ec5\DTO\ProjectMappingDTO;
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

    /**
     * ec5_228: a mapping name that collides (case-insensitive) with an existing
     * mapping name must be rejected.
     */
    public function test_rejects_duplicate_mapping_name(): void
    {
        $projectMapping = new ProjectMappingDTO();
        $projectMapping->create([
            0 => ['name' => 'My Map', 'is_default' => true, 'forms' => [], 'map_index' => 0],
        ]);

        $this->ruleMappingCreate->validate(['name' => 'my map']);
        $this->ruleMappingCreate->additionalChecks($projectMapping, ['name' => 'my map']);

        $this->assertTrue($this->ruleMappingCreate->hasErrors());
        $this->assertEquals('ec5_228', $this->ruleMappingCreate->errors['mapping'][0]);
    }

    /**
     * ec5_229: once the mapping count reaches the configured maximum, no further
     * mappings may be created.
     */
    public function test_rejects_max_mapping_count(): void
    {
        $max = config('epicollect.limits.project_mappings.max_count');
        $maps = [];
        for ($i = 0; $i < $max; $i++) {
            $maps[$i] = ['name' => 'Map ' . $i, 'is_default' => $i === 0, 'forms' => [], 'map_index' => $i];
        }

        $projectMapping = new ProjectMappingDTO();
        $projectMapping->create($maps);

        $this->ruleMappingCreate->validate(['name' => 'One Too Many']);
        $this->ruleMappingCreate->additionalChecks($projectMapping, ['name' => 'One Too Many']);

        $this->assertTrue($this->ruleMappingCreate->hasErrors());
        $this->assertEquals('ec5_229', $this->ruleMappingCreate->errors['mapping'][0]);
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
