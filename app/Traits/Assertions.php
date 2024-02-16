<?php

namespace ec5\Traits;

use ec5\Libraries\Utilities\DateFormatConverter;
use ec5\Models\Entries\BranchEntry;
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

    public function assertEntriesResponse($response, $isBranch = false)
    {
        $json = json_decode($response->getContent(), true);

        if ($isBranch) {
            $response->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'entries' => [
                        '*' => [
                            'id',
                            'type',
                            'branch_entry' => [
                                'title',
                                'answers' => [
                                    '*' => [
                                        'answer',
                                        'was_jumped'
                                    ]
                                ],
                                'created_at',
                                'entry_uuid',
                                'project_version'
                            ],
                            'attributes' => [
                                'form' => [
                                    'ref',
                                    'type'
                                ]
                            ],
                            'relationships' => [
                                'branch',
                                'parent',
                                'user' => [
                                    'data' => [
                                        'id'
                                    ]
                                ]
                            ]

                        ]
                    ]
                ],
                'meta',
                'links'
            ]);
        } else {
            $response->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'entries' => [
                        '*' => [
                            'id',
                            'type',
                            'entry' => [
                                'title',
                                'answers' => [
                                    '*' => [
                                        'answer',
                                        'was_jumped'
                                    ]
                                ],
                                'created_at',
                                'entry_uuid',
                                'project_version'
                            ],
                            'attributes' => [
                                'form' => [
                                    'ref',
                                    'type'
                                ],
                                'branch_counts',
                                'child_counts'
                            ],
                            'relationships' => [
                                'branch',
                                'parent',
                                'user' => [
                                    'data' => [
                                        'id'
                                    ]
                                ]
                            ]

                        ]
                    ]
                ],
                'meta',
                'links'
            ]);
        }

        $this->assertMeta($json['meta']);
        $this->assertLinks($json['links']);

    }

    public function assertEntriesExportResponse($response, $mapping, $params)
    {
        $json = json_decode($response->getContent(), true);

        // dd($mapping[0]['forms'][$params['form_ref']], $json['data']);
        $mappedInputs = $mapping[0]['forms'][$params['form_ref']];

        $mapTos = [];
        foreach ($mappedInputs as $inputRef => $mappedInput) {
            if (!$mappedInput['hide']) {

                if (sizeof($mappedInput['group']) > 0) {
                    //this is a group,
                    //so skip input ref map_to but grab all the group inputs map_to(s)
                    foreach ($mappedInput['group'] as $groupInputRef => $mappedGroupInput) {
                        //we need the below to skip branch inputs
                        if (in_array($groupInputRef, $params['onlyMapTheseRefs'])) {
                            $mapTos[] = $mappedGroupInput['map_to'];
                        }
                    }
                } else {
                    //we need the below to skip branch inputs
                    if (in_array($inputRef, $params['onlyMapTheseRefs'])) {
                        //not a group input, so grab the map_to
                        $mapTos[] = $mappedInput['map_to'];
                    }
                }
            }
        }

        //dd($mappedInputs, $params['onlyMapTheseRefs'], $mapTos);

        $entries = $json['data']['entries'];
        $fixedHierarchyEntryKeys = [
            'ec5_uuid',
            'created_at',
            'uploaded_at',
            'title'
        ];

        if ($params['form_index'] > 0) {
            $fixedHierarchyEntryKeys[] = 'ec5_parent_uuid';
        }

        foreach ($fixedHierarchyEntryKeys as $key) {
            foreach ($entries as $entry) {
                $this->assertArrayHasKey($key, $entry);
            }
        }

        foreach ($mapTos as $mapTo) {
            foreach ($entries as $entry) {
                $this->assertArrayHasKey($mapTo, $entry);
            }
        }
        $this->assertMeta($json['meta']);
        $this->assertLinks($json['links']);
    }


    public function assertEntriesLocationsResponse($response)
    {
        $response->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'geojson' => [
                    'type',
                    'features' => [
                        '*' => [
                            'id',
                            'type',
                            'geometry' => [
                                'type',
                                'coordinates'
                            ],
                            'properties' => [
                                'uuid',
                                'title',
                                'accuracy',
                                'created_at',
                                'possible_answers' => []
                            ]
                        ]
                    ]
                ]
            ],
            'meta' => [
                'total',
                'per_page',
                'current_page',
                'last_page',
                'from',
                'to',
                'newest',
                'oldest'
            ],
            'links' => [
                'self',
                'first',
                'prev',
                'next',
                'last'
            ]
        ]);
        $json = json_decode($response->getContent(), true);
        $this->assertMeta($json['meta']);
        $this->assertLinks($json['links']);

    }

    private function assertLinks($links)
    {
        foreach ($links as $link) {
            if ($link !== null) {
                $this->assertRegExp('/^(?:\w+:\/{2})?\w+(?:\.\w+)*(?:\/[^\s]*)?$/', $link);
            } else {
                $this->assertNull($link);
            }
        }
    }

    private function assertMeta($array)
    {
        foreach ($array as $key => $value) {
            if ($key === 'newest' || $key === 'oldest') {
                // Assert that the value is an ISO 8601 date-time string
                $this->assertRegExp('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $value);
            } else {
                // Assert that the value is an integer
                $this->assertInternalType('int', $value);
            }
        }
    }

    public function assertEntryRowAgainstPayload($entryRowBundle, $entryPayload)
    {
        $entryType = $entryPayload['data']['type'];

        $entryStructure = $entryRowBundle['entryStructure'];
        $skippedInputsRefs = $entryRowBundle['skippedInputRefs'];
        $multipleChoiceInputRefs = $entryRowBundle['multipleChoiceInputRefs'];
        $projectDefinition = $entryRowBundle['projectDefinition'];
        $project = $entryRowBundle['project'];
        $formRef = $entryPayload['data']['attributes']['form']['ref'];

        if ($entryType === config('epicollect.strings.entry_types.entry')) {
            $this->assertCount(1, Entry::where('uuid', $entryPayload['data']['id'])->get());
            $entryStored = Entry::where('uuid', $entryPayload['data']['id'])->first();
        } else {
            $this->assertCount(1, BranchEntry::where('uuid', $entryPayload['data']['id'])->get());
            $entryStored = BranchEntry::where('uuid', $entryPayload['data']['id'])->first();
        }


        $entryStoredEntryData = json_decode($entryStored->entry_data, true);
        $entryStoredGeoJsonData = json_decode($entryStored->geo_json_data, true);

        //from the entry payload, remove all skipped inputs to compare answers with entry stored
        //imp: this is done because the group input is uploaded but not saved,
        //imp: only the nested group inputs,
        //imp: and the readme is just ignored
        $entryPayloadAnswers = $entryPayload['data'][$entryType]['answers'];
        foreach ($entryPayloadAnswers as $ref => $entryPayloadAnswer) {
            if (in_array($ref, $skippedInputsRefs)) {
                unset($entryPayload['data'][$entryType]['answers'][$ref]);
            }
        }

        $this->assertEquals(
            $entryStoredEntryData[$entryType]['answers'],
            $entryPayload['data'][$entryType]['answers'],
            json_encode($projectDefinition)
        );

        foreach ($entryStoredEntryData[$entryType]['answers'] as $ref => $answer) {
            $payloadAnswer = $entryPayload['data'][$entryType]['answers'][$ref]['answer'];
            $this->assertEquals($answer['answer'], $payloadAnswer);
        }

        //assert geojson object if we have a location
        if (!is_null($entryStoredGeoJsonData)) {
            foreach ($entryStoredGeoJsonData as $inputRef => $geojson) {
                $locationAnswer = $entryPayload['data'][$entryType]['answers'][$inputRef]['answer'];
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
                    "uuid" => $entryPayload['data'][$entryType]['entry_uuid'],
                    "title" => $entryPayload['data'][$entryType]['title'],
                    "accuracy" => $locationAnswer['accuracy'],
                    "created_at" => date('Y-m-d', strtotime($entryStructure->getDateCreated())),
                    "possible_answers" => $this->getPayloadPossibleAnswers(
                        $entryPayload['data'][$entryType]['answers'],
                        $multipleChoiceInputRefs
                    )
                ]);
            }
        }

        $this->assertEquals($entryPayload['data']['id'], $entryStored->uuid);
        $this->assertEquals($entryPayload['data'][$entryType]['title'], $entryStored->title);
        $this->assertEquals($project->id, $entryStored->project_id);
        if (sizeof($entryPayload['data']['relationships']['parent']) > 0) {
            $this->assertEquals(
                $entryPayload['data']['relationships']['parent']['data']['parent_entry_uuid'],
                $entryStored->parent_uuid);
            $this->assertEquals(
                $entryPayload['data']['relationships']['parent']['data']['parent_form_ref'],
                $entryStored->parent_form_ref);
        } else {
            $this->assertEquals('', $entryStored->parent_uuid);
            $this->assertEquals('', $entryStored->parent_form_ref);
        }
        $this->assertEquals($formRef, $entryStored->form_ref);
        $this->assertEquals($entryPayload['data'][$entryType]['platform'], $entryStored->platform);
        $this->assertEquals($entryPayload['data'][$entryType]['device_id'], $entryStored->device_id);

        //assert timestamps are equal (converted as the format is different JS/MYSQL)
        $this->assertTrue(DateFormatConverter::areTimestampsEqual(
            $entryStructure->getDateCreated(),
            $entryStored->created_at)
        );

        $this->assertEquals(0, $entryStored->child_counts);
        $this->assertEquals(0, $entryStored->branch_counts);
    }

    private function getPayloadPossibleAnswers($answers, $multipleChoiceInputRefs): array
    {
        $possibleAnswers = [];
        foreach ($answers as $inputRef => $answer) {
            //need to add the possible answer, so they later added to the geojson object
            if (in_array($inputRef, $multipleChoiceInputRefs)) {
                //answer_ref comes as a string for radio and dropdown
                if (is_string($answer['answer'])) {
                    $possibleAnswers[$answer['answer']] = 1;
                } else {
                    //array for the other multiple choice type
                    foreach ($answer['answer'] as $answerRef) {
                        $possibleAnswers[$answerRef] = 1;
                    }
                }
            }
        }

        return $possibleAnswers;
    }

}
