<?php

namespace ec5\Traits;

use ec5\Libraries\Utilities\DateFormatConverter;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Assert;

trait Assertions
{
    protected function assertJsonResponseHasKeys($jsonResponse, array $expectedKeys): void
    {
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $jsonResponse, 'Key ' . $key . ' is missing in JSON response');
        }
    }

    protected function assertArrayHasExactKeys($jsonResponse, $expectedKeys): void
    {
        Assert::assertIsArray($jsonResponse);
        Assert::assertIsArray($expectedKeys);

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

    public function assertKeysNotEmpty(array $data, int $depth = PHP_INT_MAX): void
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


    public function assertIsArrayNotEmpty($data): void
    {
        Assert::assertIsArray($data);
        Assert::assertNotEmpty($data);
    }

    public function assertProjectResponse($jsonResponse): void
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

    public function assertProjectExportResponse($jsonResponse): void
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

    public function assertProjectDefinition($projectDefinition): void
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

    public function assertAvatarCreated($project): void
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
            $avatarMobile[0]
        );

        $this->assertEquals(
            $project->ref . '/' . $filename,
            $avatarThumb[0]
        );

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

    public function assertEntriesExportResponse($response, $mapping, $params, $mapIndex = 0): void
    {
        $mappedInputs = [];
        if (is_array($response)) {
            //from Guzzle response
            $json = $response;
        } else {
            //from unit test response
            $json = json_decode($response->getContent(), true);
        }

        // dd($mapping[$mapIndex]['forms'][$params['form_ref']], $json['data']);
        if (array_key_exists('branchRef', $params)) {
            //dd($mapping[$mapIndex]['forms'][$params['form_ref']], $params['branchRef']);

            foreach ($mapping[$mapIndex]['forms'][$params['form_ref']] as $ref => $inputs) {
                if ($ref === $params['branchRef']) {
                    $mappedInputs = $mapping[$mapIndex]['forms'][$params['form_ref']][$ref]['branch'];
                }
            }
        } else {
            $mappedInputs = $mapping[$mapIndex]['forms'][$params['form_ref']];
        }

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

        list($entries, $fixedEntryKeys) = $this->getFixedEntryKeys($json['data']['entries'], $params);


        foreach ($fixedEntryKeys as $key) {
            foreach ($entries as $entry) {
                $this->assertArrayHasKey($key, $entry);

                if (!is_array($response)) {
                    //public project, it must not have created_by
                    $this->assertArrayNotHasKey('created_by', $entry);
                }
            }
        }

        foreach ($mapTos as $mapTo) {
            foreach ($entries as $entry) {
                $this->assertArrayHasKey($mapTo, $entry);
            }
        }

        if (is_array($response)) {
            //Guzzle response(json)
            // Assert the structure of the JSON data
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('meta', $response);
            $this->assertArrayHasKey('links', $response);

            $this->assertArrayHasKey('id', $response['data']);
            $this->assertArrayHasKey('type', $response['data']);
            $this->assertArrayHasKey('entries', $response['data']);
            $this->assertArrayHasKey('mapping', $response['data']);

            // Check the structure of 'entries'
            Assert::assertIsArray($response['data']['entries']);
            foreach ($response['data']['entries'] as $entry) {
                Assert::assertIsArray($entry);
                //assert created_by since the project is private when using Guzzle
                $this->assertArrayHasKey('created_by', $entry);
            }

            // Check the structure of 'mapping'
            $this->assertArrayHasKey('map_name', $response['data']['mapping']);
            $this->assertArrayHasKey('map_index', $response['data']['mapping']);

        } else {
            //test response
            $response->assertJsonStructure(
                [
                    'data' => [
                        'id',
                        'type',
                        'entries' => [
                            '*' => []
                        ],
                        'mapping' => [
                            'map_name',
                            'map_index'
                        ]
                    ],
                    'meta',
                    'links'
                ]
            );
        }

        $this->assertMeta($json['meta']);
        $this->assertLinks($json['links']);
    }

    public function assertEntriesExportResponseCSV($response, $mapping, $params, $mapIndex = 0)
    {
        $access = $params['projectDefinition']['data']['project']['access'];

        $locationInputs = [];

        $forms = $params['projectDefinition']['data']['project']['forms'];
        foreach ($forms as $form) {
            $inputs = $form['inputs'];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
                    $locationInputs[] = $input['ref'];
                }
                if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                    $groupInputs = $input['group'];
                    foreach ($groupInputs as $groupInput) {
                        if ($groupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                            $locationInputs[] = $groupInput['ref'];
                        }
                    }
                }
                if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                    $branchInputs = $input['branch'];
                    foreach ($branchInputs as $branchInput) {
                        if ($branchInput['type'] === config('epicollect.strings.inputs_type.location')) {
                            $locationInputs[] = $branchInput['ref'];
                        }
                        if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {
                            $nestedGroupInputs = $branchInput['group'];
                            foreach ($nestedGroupInputs as $nestedGroupInput) {
                                if ($nestedGroupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                                    $locationInputs[] = $nestedGroupInput['ref'];
                                }
                            }
                        }
                    }
                }
            }
        }


        $mappedInputs = [];
        $json = $response;

        // dd($mapping[$mapIndex]['forms'][$params['form_ref']], $json['data']);
        if (array_key_exists('branchRef', $params)) {
            //dd($mapping[$mapIndex]['forms'][$params['form_ref']], $params['branchRef']);

            foreach ($mapping[$mapIndex]['forms'][$params['form_ref']] as $ref => $inputs) {
                if ($ref === $params['branchRef']) {
                    $mappedInputs = $mapping[$mapIndex]['forms'][$params['form_ref']][$ref]['branch'];
                }
            }
        } else {
            $mappedInputs = $mapping[$mapIndex]['forms'][$params['form_ref']];
        }

        $mapTos = [];
        foreach ($mappedInputs as $inputRef => $mappedInput) {
            if (!$mappedInput['hide']) {

                if (sizeof($mappedInput['group']) > 0) {
                    //this is a group,
                    //so skip input ref map_to but grab all the group inputs map_to(s)
                    foreach ($mappedInput['group'] as $groupInputRef => $mappedGroupInput) {
                        if (in_array($groupInputRef, $locationInputs)) {
                            //todo:handle location
                            $mapTos[] = 'lat_' . $mappedGroupInput['map_to'];
                            $mapTos[] = 'long_' . $mappedGroupInput['map_to'];
                            $mapTos[] = 'accuracy_' . $mappedGroupInput['map_to'];
                            $mapTos[] = 'UTM_Northing_' . $mappedGroupInput['map_to'];
                            $mapTos[] = 'UTM_Easting_' . $mappedGroupInput['map_to'];
                            $mapTos[] = 'UTM_Zone_' . $mappedGroupInput['map_to'];
                        } else {
                            //we need the below to skip branch inputs
                            if (in_array($groupInputRef, $params['onlyMapTheseRefs'])) {
                                $mapTos[] = $mappedGroupInput['map_to'];
                            }
                        }
                    }
                } else {
                    //we need the below to skip branch inputs
                    if (in_array($inputRef, $params['onlyMapTheseRefs'])) {

                        //if a location input,
                        if (in_array($inputRef, $locationInputs)) {
                            //todo:handle location
                            $mapTos[] = 'lat_' . $mappedInput['map_to'];
                            $mapTos[] = 'long_' . $mappedInput['map_to'];
                            $mapTos[] = 'accuracy_' . $mappedInput['map_to'];
                            $mapTos[] = 'UTM_Northing_' . $mappedInput['map_to'];
                            $mapTos[] = 'UTM_Easting_' . $mappedInput['map_to'];
                            $mapTos[] = 'UTM_Zone_' . $mappedInput['map_to'];
                        } else {
                            //not a group input, so grab the map_to
                            $mapTos[] = $mappedInput['map_to'];
                        }
                    }
                }
            }
        }

        //dd($mappedInputs, $params['onlyMapTheseRefs'], $mapTos);

        list($entries, $fixedEntryKeys) = $this->getFixedEntryKeys($json['data']['entries'], $params);


        foreach ($fixedEntryKeys as $key) {
            foreach ($entries as $entry) {
                if ($key !== 'created_by') {
                    $this->assertArrayHasKey($key, $entry);
                } else {
                    if ($access === config('epicollect.strings.project_access.public')) {
                        //public project, it must NOT have created_by
                        $this->assertArrayNotHasKey('created_by', $entry);
                    }
                    if ($access === config('epicollect.strings.project_access.private')) {
                        //private project must have created_by
                        $this->assertArrayHasKey('created_by', $entry);
                    }
                }
            }
        }

        foreach ($mapTos as $mapTo) {
            foreach ($entries as $entry) {
                $this->assertArrayHasKey($mapTo, $entry);
            }
        }
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
                $this->assertMatchesRegularExpression('/^(?:\w+:\/{2})?\w+(?:\.\w+)*(?:\/[^\s]*)?$/', $link);
            } else {
                $this->assertNull($link);
            }
        }
    }

    private function assertMeta($array): void
    {
        foreach ($array as $key => $value) {
            if ($key === 'newest' || $key === 'oldest') {
                if ($value !== null) {
                    // Assert that the value is an ISO 8601 date-time string
                    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $value);
                } else {
                    // Assert that the value is null
                    $this->assertNull($value);
                }
            } else {
                // Assert that the value is an integer
                $this->assertIsInt($value);
            }
        }
    }

    public function assertEntryRowAgainstPayload($entryRowBundle, $entryPayload): void
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
                    "created_at" => date('Y-m-d', strtotime($entryStructure->getEntryCreatedAt())),
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
                $entryStored->parent_uuid
            );
            $this->assertEquals(
                $entryPayload['data']['relationships']['parent']['data']['parent_form_ref'],
                $entryStored->parent_form_ref
            );
        } else {
            $this->assertEquals('', $entryStored->parent_uuid);
            $this->assertEquals('', $entryStored->parent_form_ref);
        }
        $this->assertEquals($formRef, $entryStored->form_ref);
        $this->assertEquals($entryPayload['data'][$entryType]['platform'], $entryStored->platform);

        if ($entryPayload['data'][$entryType]['device_id'] !== '') {
            //assert the device id is the same
            $this->assertTrue(Hash::check($entryPayload['data'][$entryType]['device_id'], $entryStored->device_id));
        } else {
            //empty, assert strings directly (should both be empty)
            $this->assertEquals($entryPayload['data'][$entryType]['device_id'], $entryStored->device_id);
        }

        //assert timestamps are equal (converted as the format is different JS/MYSQL)
        $this->assertTrue(
            DateFormatConverter::areTimestampsEqual(
                $entryStructure->getEntryCreatedAt(),
                $entryStored->created_at
            )
        );

        $this->assertEquals(0, $entryStored->child_counts);
        $this->assertEquals(0, $entryStored->branch_counts);
    }

    public function assertEntryStoredAgainstEntryPayload($entryFromDB, $entryFromPayload, $projectDefinition, $formIndex = 0): void
    {
        //for each location question, add geoJson to entryStructure
        $inputs = array_get($projectDefinition, 'data.project.forms.' . $formIndex . '.inputs');
        $inputRefsToSkip = [];
        $multipleChoiceInputRefs = [];

        foreach ($inputs as $input) {
            if (in_array(
                $input['type'],
                config('epicollect.strings.multiple_choice_question_types')
            )) {
                $multipleChoiceInputRefs[] = $input['ref'];
            }
            //imp: skip group and readme
            //if group, add answers for all the group inputs but skip the group owner
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $inputRefsToSkip[] = $input['ref'];
                //skip readme only (we cannot have nested groups)
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.readme')) {
                        $inputRefsToSkip[] = $groupInput['ref'];
                    }

                    if (in_array(
                        $groupInput['type'],
                        config('epicollect.strings.multiple_choice_question_types')
                    )) {
                        $multipleChoiceInputRefs[] = $groupInput['ref'];
                    }
                }
            }

            if ($input['type'] === config('epicollect.strings.inputs_type.readme')) {
                $inputRefsToSkip[] = $input['ref'];
            }
        }

        foreach ($entryFromPayload['answers'] as $ref => $entryPayloadAnswer) {
            if (in_array($ref, $inputRefsToSkip)) {
                unset($entryFromPayload['answers'][$ref]);
            }
        }

        $entryFromDBEntryData = json_decode($entryFromDB->entry_data, true);
        $entryFromDBGeoJsonData = json_decode($entryFromDB->geo_json_data, true);

        $possibleAnswers = [];
        foreach ($multipleChoiceInputRefs as $multipleChoiceInputRef) {
            $answer = $entryFromPayload['answers'][$multipleChoiceInputRef]['answer'];
            if (is_array($entryFromPayload['answers'][$multipleChoiceInputRef]['answer'])) {
                foreach ($answer as $value) {
                    $possibleAnswers[$value] = 1;
                }
            } else {
                $possibleAnswers[$answer] = 1;
            }

        }
        foreach ($entryFromDBGeoJsonData as $locationQuestionRef => $feature) {
            $this->assertGeoJsonData(
                $locationQuestionRef,
                $entryFromPayload,
                $feature,
                $possibleAnswers
            );
        }

        $this->assertEquals(
            $entryFromDBEntryData['entry']['answers'],
            $entryFromPayload['answers']
        );
    }

    public function assertBranchEntryStoredAgainstBranchEntryPayload($branchEntryFromDB, $branchEntryFromPayload, $projectDefinition, $branchRef, $formIndex = 0): void
    {

        //for each location question, add geoJson to entryStructure
        $inputs = array_get($projectDefinition, 'data.project.forms.' . $formIndex . '.inputs');
        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                if ($input['ref'] === $branchRef) {
                    $branchInputs = $input['branch'];
                }
            }
        }

        $branchInputRefsToSkip = [];
        $multipleChoiceBranchInputRefs = [];

        foreach ($branchInputs as $branchInput) {
            if (in_array(
                $branchInput['type'],
                config('epicollect.strings.multiple_choice_question_types')
            )) {
                $multipleChoiceBranchInputRefs[] = $branchInput['ref'];
            }
            //imp: skip group and readme
            //if group, add answers for all the group inputs but skip the group owner
            if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {
                $branchInputRefsToSkip[] = $branchInput['ref'];
                //skip readme only (we cannot have nested groups)
                $groupInputs = $branchInput['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.readme')) {
                        $branchInputRefsToSkip[] = $groupInput['ref'];
                    }

                    if (in_array(
                        $groupInput['type'],
                        config('epicollect.strings.multiple_choice_question_types')
                    )) {
                        $multipleChoiceBranchInputRefs[] = $groupInput['ref'];
                    }
                }
            }

            if ($branchInput['type'] === config('epicollect.strings.inputs_type.readme')) {
                $branchInputRefsToSkip[] = $branchInput['ref'];
            }
        }

        foreach ($branchEntryFromPayload['answers'] as $ref => $entryPayloadAnswer) {
            if (in_array($ref, $branchInputRefsToSkip)) {
                unset($branchEntryFromPayload['answers'][$ref]);
            }
        }

        $entryFromDBEntryData = json_decode($branchEntryFromDB->entry_data, true);
        $entryFromDBGeoJsonData = json_decode($branchEntryFromDB->geo_json_data, true);

        $possibleAnswers = [];
        foreach ($multipleChoiceBranchInputRefs as $multipleChoiceInputRef) {
            $answer = $branchEntryFromPayload['answers'][$multipleChoiceInputRef]['answer'];
            if (is_array($branchEntryFromPayload['answers'][$multipleChoiceInputRef]['answer'])) {
                foreach ($answer as $value) {
                    $possibleAnswers[$value] = 1;
                }
            } else {
                $possibleAnswers[$answer] = 1;
            }

        }
        foreach ($entryFromDBGeoJsonData as $locationQuestionRef => $feature) {
            $this->assertGeoJsonData(
                $locationQuestionRef,
                $branchEntryFromPayload,
                $feature,
                $possibleAnswers
            );
        }

        $this->assertEquals(
            $entryFromDBEntryData['branch_entry']['answers'],
            $branchEntryFromPayload['answers']
        );
    }


    public function assertGeoJsonData($locationQuestionRef, $entryFromPayload, $geoJsonFeature, $possibleAnswers = []): void
    {
        $locationAnswer = $entryFromPayload['answers'][$locationQuestionRef]['answer'];

        $this->assertEquals($locationAnswer['longitude'], $geoJsonFeature['geometry']['coordinates'][0]);
        $this->assertEquals($locationAnswer['latitude'], $geoJsonFeature['geometry']['coordinates'][1]);
        $this->assertEquals($locationAnswer['accuracy'], $geoJsonFeature['properties']['accuracy']);

        $this->assertIsFloat($geoJsonFeature['geometry']['coordinates'][0]);
        $this->assertIsFloat($geoJsonFeature['geometry']['coordinates'][1]);
        $this->assertEquals(round($locationAnswer['longitude'], 6), $geoJsonFeature['geometry']['coordinates'][0]);
        $this->assertEquals(round($locationAnswer['latitude'], 6), $geoJsonFeature['geometry']['coordinates'][1]);

        $this->assertEquals($entryFromPayload['entry_uuid'], $geoJsonFeature['properties']['uuid']);
        $this->assertEquals($entryFromPayload['title'], $geoJsonFeature['properties']['title']);

        //Uploaded: 2024-02-07T15:56:10.000Z
        //Actual: 2024-02-07
        $this->assertEquals(
            date('Y-m-d', strtotime($entryFromPayload['created_at'])),
            $geoJsonFeature['properties']['created_at']
        );
        if ($possibleAnswers) {
            $this->assertEquals($possibleAnswers, $geoJsonFeature['properties']['possible_answers']);
        }
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

    public function assertArraySubset(array $expectedSubset, array $actualArray): void
    {
        foreach ($expectedSubset as $key => $value) {
            $this->assertArrayHasKey($key, $actualArray);

            if (is_array($value)) {
                $this->assertArraySubset($value, $actualArray[$key]);
            } else {
                $this->assertEquals($value, $actualArray[$key]);
            }
        }
    }

    private function getFixedEntryKeys($entries1, $params): array
    {
        $entries = $entries1;
        $fixedEntryKeys = [
            'created_at',
            'uploaded_at',
            'title'
        ];

        if (array_key_exists('branchRef', $params)) {
            $fixedEntryKeys[] = 'ec5_branch_owner_uuid';
            $fixedEntryKeys[] = 'ec5_branch_uuid';
        } else {
            $fixedEntryKeys[] = 'ec5_uuid';
            if ($params['form_index'] > 0) {
                $fixedEntryKeys[] = 'ec5_parent_uuid';
            }
        }
        return array($entries, $fixedEntryKeys);
    }


}
