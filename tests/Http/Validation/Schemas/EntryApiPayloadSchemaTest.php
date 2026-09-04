<?php

namespace Tests\Http\Validation\Schemas;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Tests\TestCase;

class EntryApiPayloadSchemaTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Validator();
    }

    public function test_documented_entry_payload_matches_schema(): void
    {
        $payload = [
            'data' => [
                'id' => '3bae8c50-425e-11f1-92bc-0f179dd3fef7',
                'type' => 'entry',
                'entry' => [
                    'entry_uuid' => '3bae8c50-425e-11f1-92bc-0f179dd3fef7',
                    'created_at' => '2026-04-27T17:26:32.085Z',
                    'device_id' => '',
                    'platform' => 'WEB',
                    'title' => 'Mirko 22',
                    'answers' => [
                        '115844851c6446949b2a1b9f3d5cc7eb_69ecfdf0c0a72_69ecfdfa50ced' => [
                            'answer' => 'Mirko',
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ecfdf0c0a72_69ecfe2a50cf0' => [
                            'answer' => 22,
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ecfdf0c0a72_69ecfe0e50cee' => [
                            'answer' => [
                                'latitude' => 45.900601,
                                'longitude' => 12.004917,
                                'accuracy' => 35
                            ],
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ecfdf0c0a72_69ecfe1950cef' => [
                            'answer' => [
                                'latitude' => 45.900564,
                                'longitude' => 12.004922,
                                'accuracy' => 35
                            ],
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ecfdf0c0a72_69ecfe3850cf1' => [
                            'answer' => '',
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ecfdf0c0a72_69ecfe3950cf2' => [
                            'answer' => '',
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ecfdf0c0a72_69ecfe3b50cf3' => [
                            'answer' => '',
                            'was_jumped' => false
                        ]
                    ],
                    'project_version' => '2026-04-25 17:48:00'
                ],
                'attributes' => [
                    'form' => [
                        'ref' => '115844851c6446949b2a1b9f3d5cc7eb_69ecfdf0c0a72',
                        'type' => 'hierarchy'
                    ]
                ],
                'relationships' => [
                    'parent' => (object) [],
                    'branch' => (object) []
                ],
            ]
        ];

        $this->assertMatchesSchema('entry-payload.schema.json', $payload);
    }

    public function test_documented_child_entry_payload_matches_schema(): void
    {
        $payload = [
            'data' => [
                'id' => '21adfbe0-4260-11f1-8107-e7c795b0ed19',
                'type' => 'entry',
                'entry' => [
                    'entry_uuid' => '21adfbe0-4260-11f1-8107-e7c795b0ed19',
                    'created_at' => '2026-04-27T17:40:07.454Z',
                    'device_id' => '',
                    'platform' => 'WEB',
                    'title' => 'Tim 77',
                    'answers' => [
                        '115844851c6446949b2a1b9f3d5cc7eb_69ef9f5482716_69ecfdfa50ced' => [
                            'answer' => 'Tim',
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ef9f5482716_69ecfe2a50cf0' => [
                            'answer' => 77,
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ef9f5482716_69ecfe0e50cee' => [
                            'answer' => [
                                'latitude' => 45.900612,
                                'longitude' => 12.004866,
                                'accuracy' => 35
                            ],
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ef9f5482716_69ecfe1950cef' => [
                            'answer' => [
                                'latitude' => '',
                                'longitude' => '',
                                'accuracy' => ''
                            ],
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ef9f5482716_69ecfe3850cf1' => [
                            'answer' => '',
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ef9f5482716_69ecfe3950cf2' => [
                            'answer' => '',
                            'was_jumped' => false
                        ],
                        '115844851c6446949b2a1b9f3d5cc7eb_69ef9f5482716_69ecfe3b50cf3' => [
                            'answer' => '',
                            'was_jumped' => false
                        ]
                    ],
                    'project_version' => '2026-04-27 17:39:47'
                ],
                'attributes' => [
                    'form' => [
                        'ref' => '115844851c6446949b2a1b9f3d5cc7eb_69ef9f5482716',
                        'type' => 'hierarchy'
                    ]
                ],
                'relationships' => [
                    'parent' => [
                        'data' => [
                            'parent_form_ref' => '115844851c6446949b2a1b9f3d5cc7eb_69ecfdf0c0a72',
                            'parent_entry_uuid' => '3bae8c50-425e-11f1-92bc-0f179dd3fef7'
                        ]
                    ],
                    'branch' => (object) []
                ]
            ]
        ];

        $this->assertMatchesSchema('entry-payload.schema.json', $payload);
    }

    public function test_documented_branch_entry_payload_matches_schema(): void
    {
        $payload = [
            'data' => [
                'type' => 'branch_entry',
                'id' => '844162c0-425e-11f1-900f-1f17686d0dda',
                'attributes' => [
                    'form' => [
                        'ref' => 'ebaacb1a19194c948fa07725668ecc0a_5784e776184c6',
                        'type' => 'hierarchy'
                    ]
                ],
                'relationships' => [
                    'parent' => (object) [],
                    'branch' => [
                        'data' => [
                            'owner_input_ref' => 'ebaacb1a19194c948fa07725668ecc0a_5784e776184c6_5784e7862d022',
                            'owner_entry_uuid' => '5fca5281-aea0-497a-879f-b0f9f7ee0666'
                        ]
                    ]
                ],
                'branch_entry' => [
                    'entry_uuid' => '844162c0-425e-11f1-900f-1f17686d0dda',
                    'created_at' => '2026-04-27T17:35:43.340Z',
                    'device_id' => '',
                    'platform' => 'WEB',
                    'title' => 'John',
                    'answers' => [
                        'ebaacb1a19194c948fa07725668ecc0a_5784e776184c6_5784e7862d022_5784e7992d023' => [
                            'answer' => 'John',
                            'was_jumped' => false
                        ],
                        'ebaacb1a19194c948fa07725668ecc0a_5784e776184c6_5784e7862d022_5784e7a42d024' => [
                            'answer' => 56,
                            'was_jumped' => false
                        ],
                        'ebaacb1a19194c948fa07725668ecc0a_5784e776184c6_5784e7862d022_69d8ca88e7c39' => [
                            'answer' => '',
                            'was_jumped' => false
                        ],
                        'ebaacb1a19194c948fa07725668ecc0a_5784e776184c6_5784e7862d022_69d8d12c62b94' => [
                            'answer' => [
                                'latitude' => 45.900564,
                                'longitude' => 12.004922,
                                'accuracy' => 35
                            ],
                            'was_jumped' => false
                        ]
                    ],
                    'project_version' => '2026-04-10 12:44:56'
                ]
            ]
        ];

        $this->assertMatchesSchema('branch-entry-payload.schema.json', $payload);
    }

    public function test_documented_file_entry_payload_matches_schema(): void
    {
        $payload = [
            'data' => [
                'type' => 'file_entry',
                'id' => '941c3f6d-025c-49b5-b1e9-dd727d38ec98',
                'attributes' => [
                    'form' => [
                        'ref' => '3f15caf2bfc8480e9cca098435dbf8d3_59527e36cf2a1',
                        'type' => 'hierarchy'
                    ]
                ],
                'relationships' => [
                    'parent' => [
                        'data' => [
                            'parent_form_ref' => '',
                            'parent_entry_uuid' => ''
                        ]
                    ],
                    'branch' => [
                        'data' => [
                            'owner_input_ref' => '',
                            'owner_entry_uuid' => ''
                        ]
                    ]
                ],
                'file_entry' => [
                    'entry_uuid' => '941c3f6d-025c-49b5-b1e9-dd727d38ec98',
                    'name' => '941c3f6d-025c-49b5-b1e9-dd727d38ec98_1775752749.jpg',
                    'type' => 'photo',
                    'input_ref' => '3f15caf2bfc8480e9cca098435dbf8d3_59527e36cf2a1_59527e36cf2a2',
                    'project_version' => '2026-04-09 18:31:16',
                    'created_at' => '2026-04-09T16:39:09.000Z',
                    'device_id' => 'android-device-id',
                    'platform' => 'Android'
                ]
            ]
        ];

        $this->assertMatchesSchema('file-entry-payload.schema.json', $payload);
    }

    public function test_documented_delete_payload_matches_schema(): void
    {
        $payload = [
            'data' => [
                'type' => 'delete',
                'id' => '941c3f6d-025c-49b5-b1e9-dd727d38ec98',
                'attributes' => [
                    'form' => [
                        'ref' => '3f15caf2bfc8480e9cca098435dbf8d3_59527e36cf2a1',
                        'type' => 'hierarchy'
                    ],
                    'branch_counts' => null,
                    'child_counts' => 0
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
                    ],
                    'user' => [
                        'data' => [
                            'id' => 1
                        ]
                    ]
                ],
                'delete' => [
                    'entry_uuid' => '941c3f6d-025c-49b5-b1e9-dd727d38ec98'
                ]
            ]
        ];

        $this->assertMatchesSchema('delete-entry-payload.schema.json', $payload);
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
}
