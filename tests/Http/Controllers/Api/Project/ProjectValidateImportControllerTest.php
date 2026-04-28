<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Libraries\Utilities\Generators;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectValidateImportControllerTest extends TestCase
{
    private const string ROUTE = 'api/import/project/validate';
    private const string VALID_TOKEN = 'test-import-validation-token';

    public function setUp(): void
    {
        parent::setUp();
        // Inject a known token so tests are deterministic and independent of .env
        config()->set('epicollect.setup.api.import_project.validation_key', self::VALID_TOKEN);
    }

    /**
     * Build the smallest project definition payload that satisfies every JSON
     * Schema constraint (format, enum, pattern, etc.) so the happy-path test is
     * never affected by random data from ProjectDefinitionGenerator.
     */
    private function minimalValidPayload(): array
    {
        $projectRef = Generators::projectRef();
        $formRef    = Generators::formRef($projectRef);
        $inputRef   = Generators::inputRef($formRef);

        return [
            'id'   => $projectRef,
            'type' => 'project',
            'project' => [
                'ref'               => $projectRef,
                'name'              => 'EC5 ValidImport Test',
                'slug'              => 'ec5-validimport-test',
                'access'            => 'public',
                'status'            => 'active',
                'category'          => 'general',
                'visibility'        => 'hidden',
                'description'       => 'A minimal project for schema-validation tests.',
                'small_description' => 'Minimal test project.',
                'homepage'          => config('app.url') . '/project/ec5-validimport-test',
                'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
                'entries_limits'    => [],
                'can_bulk_upload'   => 'nobody',
                'forms' => [
                    [
                        'ref'    => $formRef,
                        'name'   => 'Form One',
                        'slug'   => 'form-one',
                        'type'   => 'hierarchy',
                        'inputs' => [
                            [
                                'ref'                    => $inputRef,
                                'type'                   => 'text',
                                'question'               => 'What is your name?',
                                'is_title'               => true,
                                'is_required'            => false,
                                'uniqueness'             => 'none',
                                'verify'                 => false,
                                'jumps'                  => [],
                                'possible_answers'       => [],
                                'branch'                 => [],
                                'group'                  => [],
                                'regex'                  => null,
                                'default'                => '',
                                'max'                    => null,
                                'min'                    => null,
                                'datetime_format'        => null,
                                'set_to_current_datetime' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function minimalValidMappingPayload(string $mappingName = 'Imported Mapping'): array
    {
        $payload = $this->minimalValidPayload();
        $formRef = $payload['project']['forms'][0]['ref'];
        $inputRef = $payload['project']['forms'][0]['inputs'][0]['ref'];

        return [
            'name' => $mappingName,
            'forms' => [
                $formRef => [
                    $inputRef => [
                        'hide' => false,
                        'group' => [],
                        'branch' => [],
                        'map_to' => 'imported_name',
                        'possible_answers' => []
                    ]
                ]
            ],
            'map_index' => 0,
            'is_default' => true
        ];
    }

    // -------------------------------------------------------------------------
    // Token gate
    // -------------------------------------------------------------------------

    public function test_rejects_request_with_no_token(): void
    {
        // No Authorization header at all
        $response = $this->json('POST', self::ROUTE, []);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    ['code', 'title', 'source']
                ]
            ]);

        $errors = $response->json('errors');
        $this->assertCount(1, $errors);
        $this->assertEquals('ec5_257', $errors[0]['code']);
        $this->assertEquals('error', $errors[0]['source']);
    }

    public function test_rejects_request_with_invalid_token(): void
    {
        $response = $this->json('POST', self::ROUTE, [], [
            'Authorization' => 'Bearer wrong-token'
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    ['code', 'title', 'source']
                ]
            ]);

        $errors = $response->json('errors');
        $this->assertCount(1, $errors);
        $this->assertEquals('ec5_257', $errors[0]['code']);
        $this->assertEquals('error', $errors[0]['source']);
    }

    // -------------------------------------------------------------------------
    // ImportJsonValidator (basic structure) errors
    // -------------------------------------------------------------------------

    public function test_rejects_payload_missing_data_key(): void
    {
        // Payload has no 'data' key at all
        $response = $this->json('POST', self::ROUTE, ['foo' => 'bar'], [
            'Authorization' => 'Bearer ' . self::VALID_TOKEN
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    ['code', 'title', 'source']
                ]
            ]);

        $codes = array_column($response->json('errors'), 'code');
        $this->assertContains('ec5_269', $codes);
    }

    public function test_rejects_payload_with_missing_data_type(): void
    {
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);

        // Remove the 'type' key from data so ImportJsonValidator fires
        unset($projectDefinition['data']['type']);

        $response = $this->json('POST', self::ROUTE, $projectDefinition, [
            'Authorization' => 'Bearer ' . self::VALID_TOKEN
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    ['code', 'title', 'source']
                ]
            ]);

        $codes = array_column($response->json('errors'), 'code');
        $this->assertContains('ec5_281', $codes);
    }

    // -------------------------------------------------------------------------
    // JSON Schema validation (ProjectSchemaValidator) errors
    // -------------------------------------------------------------------------

    public function test_rejects_payload_with_schema_violation(): void
    {
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);

        // Inject an invalid enum value to trigger a schema violation
        $projectDefinition['data']['project']['category'] = 'invalid-category-xyz';

        $response = $this->json('POST', self::ROUTE, $projectDefinition, [
            'Authorization' => 'Bearer ' . self::VALID_TOKEN
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    ['schema', 'title', 'source']
                ]
            ]);

        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
        // Every error entry must carry 'source' = 'schema-validator'
        foreach ($errors as $error) {
            $this->assertEquals('project-json-validator', $error['source']);
            $this->assertNotEmpty($error['schema']);
            $this->assertNotEmpty($error['title']);
        }
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_accepts_valid_payload_and_returns_schema_success(): void
    {
        $projectDefinition = ['data' => $this->minimalValidPayload()];

        $response = $this->json('POST', self::ROUTE, $projectDefinition, [
            'Authorization' => 'Bearer ' . self::VALID_TOKEN
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'id',
                    'project' => ['name', 'slug'],
                    'validation',
                    'schema',
                    'validated_at',
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals('project-json-validator', $data['type']);
        $this->assertEquals('passed', $data['validation']);
        $this->assertNotEmpty($data['id']);
        $this->assertNotEmpty($data['schema']);
        $this->assertNotEmpty($data['validated_at']);
        $this->assertNotEmpty($data['project']['name']);
        $this->assertNotEmpty($data['project']['slug']);
    }

    public function test_accepts_valid_payload_with_custom_project_mapping(): void
    {
        $projectDefinition = [
            'data' => $this->minimalValidPayload(),
            'meta' => [
                'project_mapping' => [
                    $this->minimalValidMappingPayload('Imported Mapping')
                ]
            ]
        ];

        $response = $this->json('POST', self::ROUTE, $projectDefinition, [
            'Authorization' => 'Bearer ' . self::VALID_TOKEN
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'id',
                    'project' => ['name', 'slug'],
                    'validation',
                    'schema',
                    'validated_at',
                ]
            ]);

        $this->assertEquals('passed', $response->json('data.validation'));
    }

    public function test_accepts_valid_payload_with_ec5_auto_project_mapping(): void
    {
        $projectDefinition = [
            'data' => $this->minimalValidPayload(),
            'meta' => [
                'project_mapping' => [
                    $this->minimalValidMappingPayload('EC5_AUTO')
                ]
            ]
        ];

        $response = $this->json('POST', self::ROUTE, $projectDefinition, [
            'Authorization' => 'Bearer ' . self::VALID_TOKEN
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'id',
                    'project' => ['name', 'slug'],
                    'validation',
                    'schema',
                    'validated_at',
                ]
            ]);

        $this->assertEquals('passed', $response->json('data.validation'));
    }

    public function test_rejects_payload_with_invalid_project_mapping(): void
    {
        $invalidMapping = $this->minimalValidMappingPayload('Imported Mapping');
        $invalidMapping['forms'] = [
            'invalid_form_ref' => $invalidMapping['forms'][array_key_first($invalidMapping['forms'])]
        ];

        $projectDefinition = [
            'data' => $this->minimalValidPayload(),
            'meta' => [
                'project_mapping' => [
                    $invalidMapping
                ]
            ]
        ];

        $response = $this->json('POST', self::ROUTE, $projectDefinition, [
            'Authorization' => 'Bearer ' . self::VALID_TOKEN
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'errors' => [
                    ['code', 'title', 'source']
                ]
            ]);

        $errors = $response->json('errors');
        $this->assertCount(1, $errors);
        $this->assertEquals('ec5_15', $errors[0]['code']);
        $this->assertEquals('invalid_form_ref', $errors[0]['source']);
    }
}
