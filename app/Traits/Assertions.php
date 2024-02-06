<?php

namespace ec5\Traits;

use ec5\Libraries\Utilities\DateFormatConverter;
use ec5\Models\Entries\Entry;
use Illuminate\Support\Facades\Storage;
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

    public function assertProjectExportResponse($jsonResponse)
    {
        $this->assertIsArrayNotEmpty($jsonResponse['meta']['project_mapping']);
        $this->assertIsArrayNotEmpty($jsonResponse['meta']['project_stats']);
        $this->assertIsArrayNotEmpty($jsonResponse['data']);

        $this->assertArrayHasExactKeys(
            $jsonResponse['meta']['project_stats'],
            config('testing.JSON_STRUCTURES_KEYS.project_stats')
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

    public function assertAvatarCreated($project)
    {
        //assert avatar is created
        $filename = config('epicollect.media.project_avatar.filename');
        $avatarMobile = Storage::disk('project_mobile_logo')->files($project->ref);
        $avatarThumb = Storage::disk('project_thumb')->files($project->ref);

        $this->assertGreaterThan(0, count($avatarMobile));
        $this->assertCount(1, $avatarMobile);
        $this->assertGreaterThan(0, count($avatarThumb));
        $this->assertCount(1, $avatarThumb);

        $this->assertEquals(
            $project->ref . '/' . $filename,
            $avatarMobile[0]);

        $this->assertEquals(
            $project->ref . '/' . $filename,
            $avatarThumb[0]);

        //delete fake avatar files
        Storage::disk('project_mobile_logo')->deleteDirectory($project->ref);
        Storage::disk('project_thumb')->deleteDirectory($project->ref);
    }

    public function assertEntryRow($projectDefinition, $project, $entryStructure, $entryPayload, $skippedInputsRefs, $formRef)
    {
        $this->assertCount(1, Entry::where('uuid', $entryPayload['data']['id'])->get());
        $entryStored = Entry::where('uuid', $entryPayload['data']['id'])->first();
        $entryStoredEntryData = json_decode($entryStored->entry_data, true);
        $entryStoredGeoJsonData = json_decode($entryStored->geo_json_data, true);

        //from the entry payload, remove all skipped inputs to compare answers with entry stored
        //imp: this is done because the group input is uploaded but not saved,
        //imp: only the nested group inputs,
        //imp: and the readme is just ignored
        $entryPayloadAnswers = $entryPayload['data']['entry']['answers'];
        foreach ($entryPayloadAnswers as $ref => $entryPayloadAnswer) {
            if (in_array($ref, $skippedInputsRefs)) {
                unset($entryPayload['data']['entry']['answers'][$ref]);
            }
        }

        $this->assertEquals(
            $entryStoredEntryData['entry']['answers'],
            $entryPayload['data']['entry']['answers'],
            json_encode($projectDefinition)
        );

        foreach ($entryStoredEntryData['entry']['answers'] as $ref => $answer) {
            $payloadAnswer = $entryPayload['data']['entry']['answers'][$ref]['answer'];
            $this->assertEquals($answer['answer'], $payloadAnswer);
        }

        //assert geojson object
        foreach ($entryStoredGeoJsonData as $inputRef => $geojson) {
            $locationAnswer = $entryPayload['data']['entry']['answers'][$inputRef]['answer'];
            $this->assertEquals($geojson['id'], $entryPayload['data']['id']);
            $this->assertEquals('Feature', $geojson['type']);
            $this->assertEquals($geojson['geometry'], [
                'type' => 'Point',
                'coordinates' => [
                    $locationAnswer['longitude'],
                    $locationAnswer['latitude']
                ]
            ]);

            $this->assertEquals($geojson['properties'], [
                "uuid" => $entryPayload['data']['entry']['entry_uuid'],
                "title" => $entryPayload['data']['entry']['title'],
                "accuracy" => $locationAnswer['accuracy'],
                "created_at" => date('Y-m-d', strtotime($entryStructure->getDateCreated())),
                "possible_answers" => []
            ]);
        }

        $this->assertEquals($entryPayload['data']['id'], $entryStored->uuid);
        $this->assertEquals($entryPayload['data']['entry']['title'], $entryStored->title);
        $this->assertEquals($project->id, $entryStored->project_id);
        if (sizeof($entryPayload['data']['relationships']['parent']) > 0) {
            $this->assertEquals(
                $entryPayload['data']['relationships']['parent']['parent_entry_uuid'],
                $entryStored->parent_uuid);
            $this->assertEquals(
                $entryPayload['data']['relationships']['parent']['parent_form_ref'],
                $entryStored->parent_form_ref);
        } else {
            $this->assertEquals('', $entryStored->parent_uuid);
            $this->assertEquals('', $entryStored->parent_form_ref);
        }
        $this->assertEquals($formRef, $entryStored->form_ref);
        $this->assertEquals($entryPayload['data']['entry']['platform'], $entryStored->platform);
        $this->assertEquals($entryPayload['data']['entry']['device_id'], $entryStored->device_id);

        //assert timestamps are equal (converted as the format is different JS/MYSQL)
        $this->assertTrue(DateFormatConverter::areTimestampsEqual(
            $entryStructure->getDateCreated(),
            $entryStored->created_at)
        );
      
        $this->assertEquals(0, $entryStored->child_counts);
        $this->assertEquals(0, $entryStored->branch_counts);
    }
}