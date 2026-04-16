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
