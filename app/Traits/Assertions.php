<?php

namespace ec5\Traits;

use PHPUnit\Framework\Assert;

trait Assertions
{

    protected function assertJsonResponseHasKeys($jsonResponse, array $expectedKeys)
    {
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $jsonResponse, 'Key ' . $key . ' is missing in JSON response');
        }
    }

    protected function assertArrayHasExactKeys($jsonResponse, $expectedKeys)
    {
        $this->assertIsArray($jsonResponse);
        $this->assertIsArray($expectedKeys);

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $jsonResponse,
                "Expected key '$key' is missing in the JSON response"
            );
        }

        $extraKeys = array_diff(array_keys($jsonResponse), $expectedKeys);
        $this->assertEmpty(
            $extraKeys,
            'JSON response contains unexpected keys: ' . implode(', ', $extraKeys)
        );
    }

    public function assertKeysNotEmpty(array $data, int $depth = PHP_INT_MAX)
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && $depth > 1) {
                $this->assertKeysNotEmpty($value);
            } else {
                if ($value === null || $value === '') {
                    Assert::fail('Key ' . $key . ' has an empty value.');
                }
            }
        }
    }

    public function assertIsArray($actual, $message = '')
    {
        Assert::assertInternalType('array', $actual, $message);
    }

    public function assertIsArrayNotEmpty($data)
    {
        self::assertIsArray($data);
        Assert::assertNotEmpty($data);
    }

    public function assertProjectResponse($jsonResponse)
    {
        $this->assertIsArrayNotEmpty($jsonResponse['meta']['project_extra']);
        $this->assertIsArrayNotEmpty($jsonResponse['meta']['project_user']);
        $this->assertIsArrayNotEmpty($jsonResponse['meta']['project_mapping']);
        $this->assertIsArrayNotEmpty($jsonResponse['meta']['project_stats']);
        $this->assertIsArrayNotEmpty($jsonResponse['data']);

        $this->assertArrayHasExactKeys(
            $jsonResponse['meta']['project_extra'],
            config('testing.JSON_STRUCTURES_KEYS.project_extra.root')
        );

        //todo: project_extra nested arrays

        $this->assertArrayHasExactKeys(
            $jsonResponse['meta']['project_stats'],
            config('testing.JSON_STRUCTURES_KEYS.project_stats')
        );

        $this->assertArrayHasExactKeys(
            $jsonResponse['meta']['project_user'],
            config('testing.JSON_STRUCTURES_KEYS.project_user')
        );

        foreach ($jsonResponse['meta']['project_mapping'] as $mapping) {
            $this->assertArrayHasExactKeys(
                $mapping,
                config('testing.JSON_STRUCTURES_KEYS.project_mapping')
            );
        }

        $this->assertProjectDefinition($jsonResponse['data']);

        $this->assertKeysNotEmpty($jsonResponse, 1);
    }

    public function assertProjectDefinition($projectDefinition)
    {

        $this->assertArrayHasExactKeys(
            $projectDefinition,
            config('testing.JSON_STRUCTURES_KEYS.project_definition.root')
        );

        $this->assertArrayHasExactKeys(
            $projectDefinition['project'],
            config('testing.JSON_STRUCTURES_KEYS.project_definition.project')
        );

        foreach ($projectDefinition['project']['forms'] as $form) {
            $this->assertArrayHasExactKeys(
                $form,
                config('testing.JSON_STRUCTURES_KEYS.project_definition.forms')
            );
            foreach ($form['inputs'] as $input) {
                $this->assertArrayHasExactKeys(
                    $input,
                    config('testing.JSON_STRUCTURES_KEYS.project_definition.inputs')
                );
                //assert possible_answers
                foreach ($input['possible_answers'] as $possibleAnswer) {
                    $this->assertArrayHasExactKeys(
                        $possibleAnswer,
                        config('testing.JSON_STRUCTURES_KEYS.project_definition.possible_answers')
                    );
                }
                //assert jumps
                foreach ($input['jumps'] as $jump) {
                    $this->assertArrayHasExactKeys(
                        $jump,
                        config('testing.JSON_STRUCTURES_KEYS.project_definition.jumps')
                    );
                }
                //assert group
                foreach ($input['group'] as $groupInput) {
                    $this->assertArrayHasExactKeys(
                        $groupInput,
                        config('testing.JSON_STRUCTURES_KEYS.project_definition.inputs')
                    );
                    //assert possible_answers
                    foreach ($groupInput['possible_answers'] as $possibleAnswer) {
                        $this->assertArrayHasExactKeys(
                            $possibleAnswer,
                            config('testing.JSON_STRUCTURES_KEYS.project_definition.possible_answers')
                        );
                    }
                }
                //assert branch
                foreach ($input['branch'] as $branchInput) {
                    $this->assertArrayHasExactKeys(
                        $branchInput,
                        config('testing.JSON_STRUCTURES_KEYS.project_definition.inputs')
                    );
                    //assert possible_answers
                    foreach ($branchInput['possible_answers'] as $possibleAnswer) {
                        $this->assertArrayHasExactKeys(
                            $possibleAnswer,
                            config('testing.JSON_STRUCTURES_KEYS.project_definition.possible_answers')
                        );
                    }
                    foreach ($branchInput['jumps'] as $jump) {
                        $this->assertArrayHasExactKeys(
                            $jump,
                            config('testing.JSON_STRUCTURES_KEYS.project_definition.jumps')
                        );
                    }
                }
            }
        }

    }
}