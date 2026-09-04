<?php

namespace Tests\Http\Validation\Schemas;

use ec5\DTO\EntryStructureDTO;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Tests\TestCase;

class EntryStorageSchemaTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Validator();
    }

    public function test_documented_entry_data_example_matches_schema(): void
    {
        $entryData = [
            'id' => '3ac0f40b-5ca2-4c29-8db4-c9758784128a',
            'type' => 'entry',
            'entry' => [
                'title' => 'Corporis voluptatem soluta quisquam sit odio voluptas est.',
                'answers' => [
                    '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e066e710d' => [
                        'answer' => 'Corporis voluptatem soluta quisquam sit odio voluptas est.',
                        'was_jumped' => false
                    ],
                    '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e072e710e' => [
                        'answer' => 49927473,
                        'was_jumped' => false
                    ],
                    '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e07ce710f' => [
                        'answer' => '1960-10-11T00:00:00.000Z',
                        'was_jumped' => false
                    ],
                    '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e64ee712a' => [
                        'answer' => '1970-01-01T14:40:40.000Z',
                        'was_jumped' => false
                    ],
                    '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5810ba45ae824' => [
                        'answer' => [
                            'accuracy' => 7,
                            'latitude' => 61.886241,
                            'longitude' => -65.348699
                        ],
                        'was_jumped' => false
                    ],
                    '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5810ba4bae825' => [
                        'answer' => '3ac0f40b-5ca2-4c29-8db4-c9758784128a_1775752749.jpg',
                        'was_jumped' => false
                    ],
                    '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e0b6e7118_5784e0cae7119' => [
                        'answer' => '5784e0e6e711f',
                        'was_jumped' => false
                    ],
                    '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e0b6e7118_5784e0f4e7121' => [
                        'answer' => [
                            '5784e108e7124'
                        ],
                        'was_jumped' => false
                    ]
                ],
                'created_at' => '2026-04-09T16:39:09.000Z',
                'entry_uuid' => '3ac0f40b-5ca2-4c29-8db4-c9758784128a',
                'project_version' => '2026-04-09 18:31:16'
            ],
            'attributes' => [
                'form' => [
                    'ref' => '1e7640c890164034a4cff02ba2d99a52_5784e0609397d',
                    'type' => 'hierarchy'
                ]
            ],
            'relationships' => [
                'branch' => [
                    'data' => [
                        'owner_input_ref' => '',
                        'owner_entry_uuid' => ''
                    ]
                ],
                'parent' => [
                    'data' => [
                        'parent_form_ref' => '',
                        'parent_entry_uuid' => ''
                    ]
                ]
            ]
        ];

        $this->assertMatchesSchema('entry-data.schema.json', $entryData);
    }

    public function test_documented_geo_json_data_example_matches_schema(): void
    {
        $geoJsonData = [
            '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5810ba45ae824' => [
                'id' => '3ac0f40b-5ca2-4c29-8db4-c9758784128a',
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        -65.348699,
                        61.886241
                    ]
                ],
                'properties' => [
                    'uuid' => '3ac0f40b-5ca2-4c29-8db4-c9758784128a',
                    'title' => 'Corporis voluptatem soluta quisquam sit odio voluptas est.',
                    'accuracy' => 7,
                    'created_at' => '2026-04-09',
                    'possible_answers' => [
                        '5784e0e6e711f' => 1,
                        '5784e108e7124' => 1
                    ]
                ]
            ]
        ];

        $this->assertMatchesSchema('geo-json-data.schema.json', $geoJsonData);
    }

    public function test_entry_structure_validated_entry_matches_entry_data_schema(): void
    {
        $entryStructure = new EntryStructureDTO();
        $entryStructure->createStructure($this->makeEntryPayload());

        $entryStructure->addAnswerToEntry($this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e066e710d', 'text'), [
            'answer' => 'Corporis voluptatem soluta quisquam sit odio voluptas est.',
            'was_jumped' => false
        ]);
        $entryStructure->addAnswerToEntry($this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e072e710e', 'integer'), [
            'answer' => 49927473,
            'was_jumped' => false
        ]);
        $entryStructure->addAnswerToEntry($this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e07ce710f', 'date'), [
            'answer' => '1960-10-11T00:00:00.000Z',
            'was_jumped' => false
        ]);
        $entryStructure->addAnswerToEntry($this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e64ee712a', 'time'), [
            'answer' => '1970-01-01T14:40:40.000Z',
            'was_jumped' => false
        ]);
        $entryStructure->addAnswerToEntry($this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5810ba45ae824', 'location'), [
            'answer' => [
                'accuracy' => 7,
                'latitude' => 61.886241,
                'longitude' => -65.348699
            ],
            'was_jumped' => false
        ]);
        $entryStructure->addGeoJsonObject(
            $this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5810ba45ae824', 'location'),
            [
                'accuracy' => 7,
                'latitude' => 61.886241,
                'longitude' => -65.348699
            ]
        );
        $entryStructure->addAnswerToEntry($this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5810ba4bae825', 'photo'), [
            'answer' => '3ac0f40b-5ca2-4c29-8db4-c9758784128a_1775752749.jpg',
            'was_jumped' => false
        ]);
        $entryStructure->addAnswerToEntry($this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e0b6e7118_5784e0cae7119', 'radio'), [
            'answer' => '5784e0e6e711f',
            'was_jumped' => false
        ]);
        $entryStructure->addAnswerToEntry($this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e0b6e7118_5784e0f4e7121', 'checkbox'), [
            'answer' => ['5784e108e7124'],
            'was_jumped' => false
        ]);

        $entryStructure->addPossibleAnswer('5784e0e6e711f');
        $entryStructure->addPossibleAnswer('5784e108e7124');
        $entryStructure->addPossibleAnswersToGeoJson();

        $this->assertMatchesSchema('entry-data.schema.json', $entryStructure->getValidatedEntry());
        $this->assertMatchesSchema('geo-json-data.schema.json', $entryStructure->getGeoJson());
    }

    public function test_branch_entry_structure_matches_entry_data_schema(): void
    {
        $entryStructure = new EntryStructureDTO();
        $entryStructure->createStructure([
            'id' => '3ac0f40b-5ca2-4c29-8db4-c9758784128b',
            'type' => 'branch_entry',
            'branch_entry' => [
                'title' => 'Branch entry title',
                'created_at' => '2026-04-09T16:39:09.000Z',
                'entry_uuid' => '3ac0f40b-5ca2-4c29-8db4-c9758784128b',
                'project_version' => '2026-04-09 18:31:16',
                'answers' => []
            ],
            'attributes' => [
                'form' => [
                    'ref' => '1e7640c890164034a4cff02ba2d99a52_5784e0609397d',
                    'type' => 'hierarchy'
                ]
            ],
            'relationships' => [
                'branch' => [
                    'data' => [
                        'owner_input_ref' => '1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e0b6e7118',
                        'owner_entry_uuid' => '3ac0f40b-5ca2-4c29-8db4-c9758784128a'
                    ]
                ],
                'parent' => [
                    'data' => [
                        'parent_form_ref' => '',
                        'parent_entry_uuid' => ''
                    ]
                ]
            ]
        ]);

        $entryStructure->addAnswerToEntry(
            $this->makeInput('1e7640c890164034a4cff02ba2d99a52_5784e0609397d_5784e0b6e7118_5784e0cae7119', 'radio'),
            [
                'answer' => '5784e0e6e711f',
                'was_jumped' => false
            ]
        );

        $this->assertMatchesSchema('entry-data.schema.json', $entryStructure->getValidatedEntry());
    }

    private function assertMatchesSchema(string $schemaFile, array $data): void
    {
        $schema = json_decode(file_get_contents(public_path("schemas/$schemaFile")));
        $payload = json_decode(json_encode($data));
        $result = $this->validator->validate($payload, $schema, null, ['maxErrors' => 0]);

        if (!$result->isValid()) {
            $formatter = new ErrorFormatter();
            $errors = $formatter->format($result->error(), true);
            $this->fail(json_encode($errors, JSON_PRETTY_PRINT));
        }

        $this->assertTrue($result->isValid());
    }

    private function makeEntryPayload(): array
    {
        return [
            'id' => '3ac0f40b-5ca2-4c29-8db4-c9758784128a',
            'type' => 'entry',
            'entry' => [
                'title' => 'Corporis voluptatem soluta quisquam sit odio voluptas est.',
                'created_at' => '2026-04-09T16:39:09.000Z',
                'entry_uuid' => '3ac0f40b-5ca2-4c29-8db4-c9758784128a',
                'project_version' => '2026-04-09 18:31:16',
                'answers' => []
            ],
            'attributes' => [
                'form' => [
                    'ref' => '1e7640c890164034a4cff02ba2d99a52_5784e0609397d',
                    'type' => 'hierarchy'
                ]
            ],
            'relationships' => [
                'branch' => [
                    'data' => [
                        'owner_input_ref' => '',
                        'owner_entry_uuid' => ''
                    ]
                ],
                'parent' => [
                    'data' => [
                        'parent_form_ref' => '',
                        'parent_entry_uuid' => ''
                    ]
                ]
            ]
        ];
    }

    private function makeInput(string $ref, string $type): array
    {
        return [
            'ref' => $ref,
            'type' => $type
        ];
    }
}
