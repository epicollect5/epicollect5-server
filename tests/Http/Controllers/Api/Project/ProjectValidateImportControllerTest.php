<?php

namespace Tests\Http\Controllers\Api\Project;

use ec5\Libraries\Utilities\Generators;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProjectValidateImportControllerTest extends TestCase
{
    private const string ROUTE = '/api/import/project/validate';
    private const string VALID_TOKEN = 'test-import-validation-token';
    private const string ANSWER_REF = 'a4f9b3c2d1e6f'; // 13-char lowercase hex

    public function setUp(): void
    {
        parent::setUp();
        // Inject a known token so tests are deterministic and independent of .env
        config()->set('epicollect.setup.api.import_project.validation_key', self::VALID_TOKEN);
    }

    private function postValidateImport(array $payload = [], ?string $token = self::VALID_TOKEN): TestResponse
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];

        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $request = Request::create(self::ROUTE, 'POST', $payload, [], [], $server);
        $response = $this->app->make(Kernel::class)->handle($request);

        return TestResponse::fromBaseResponse($response);
    }

    /**
     * Assert a 400 response whose errors each carry the given ec5 code in the
     * `code` field. Used for token / importJson / definition / mapping failures,
     * all of which are surfaced via Response::apiErrorCode.
     */
    private function assertEc5Code(TestResponse $response, string $code): void
    {
        $response->assertStatus(400);
        $codes = array_column($response->json('errors'), 'code');
        $this->assertContains($code, $codes, "Expected ec5 code $code in: " . $response->getContent());
    }

    /**
     * Assert a 400 response produced by the JSON Schema validator (step 3),
     * which emits errors with source = 'project-json-validator' (no `code`).
     */
    private function assertSchemaViolation(TestResponse $response, ?string $jsonPointer = null): void
    {
        $response->assertStatus(400);
        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
        foreach ($errors as $error) {
            $this->assertEquals('project-json-validator', $error['source']);
        }
        if ($jsonPointer !== null) {
            $found = false;
            foreach ($errors as $error) {
                if (str_starts_with($error['title'], $jsonPointer)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Expected a schema violation at '$jsonPointer' in: " . $response->getContent());
        }
    }

    /**
     * Build a schema-valid project payload with the given top-level inputs.
     */
    private function payloadWithInputs(array $inputs, array $projectOverrides = [], array $basePayload = null): array
    {
        $project = $basePayload ?? $this->minimalValidPayload();
        $project['project']['forms'][0]['inputs'] = $inputs;
        foreach ($projectOverrides as $key => $value) {
            $project['project'][$key] = $value;
        }
        return ['data' => $project];
    }

    private function wrapWithMapping(array $payload, array $mapping): array
    {
        $payload['meta'] = ['project_mapping' => [$mapping]];
        return $payload;
    }

    /**
     * Build a schema- and app-valid input reference. Mirrors
     * Generators::inputRef($scope) (formRef for top-level, parent input ref for
     * nested) so isValidRef accepts it.
     */
    private function ref(string $scope): string
    {
        return $scope . '_' . uniqid();
    }

    /**
     * Build a valid input of the given type. $overrides wins over type defaults.
     */
    private function makeInput(string $type, string $inputRef, string $formRef, array $overrides = []): array
    {
        $input = [
            'ref' => $inputRef,
            'type' => $type,
            'question' => 'Question?',
            'is_title' => false,
            'is_required' => false,
            'uniqueness' => 'none',
            'verify' => false,
            'jumps' => [],
            'possible_answers' => [],
            'branch' => [],
            'group' => [],
            'regex' => null,
            'default' => '',
            'max' => null,
            'min' => null,
            'datetime_format' => null,
            'set_to_current_datetime' => false,
        ];

        switch ($type) {
            case 'integer':
            case 'decimal':
                // numeric defaults are null/empty, which is valid
                break;
            case 'date':
                $input['datetime_format'] = 'YYYY/MM/dd';
                break;
            case 'time':
                $input['datetime_format'] = 'HH:mm:ss';
                break;
            case 'dropdown':
            case 'radio':
            case 'checkbox':
            case 'searchsingle':
            case 'searchmultiple':
                $input['possible_answers'] = [
                    ['answer_ref' => self::ANSWER_REF, 'answer' => 'Answer A'],
                ];
                break;
            case 'group':
                $input['group'] = [$this->makeInput('text', $this->ref($inputRef), $formRef)];
                break;
            case 'branch':
                $input['branch'] = [$this->makeInput('text', $this->ref($inputRef), $formRef)];
                break;
        }

        return array_merge($input, $overrides);
    }

    /**
     * Build the smallest project definition payload that satisfies every JSON
     * Schema constraint (format, enum, pattern, etc.) so the happy-path test is
     * never affected by random data from ProjectDefinitionGenerator.
     */
    private function minimalValidPayload(): array
    {
        $projectRef = Generators::projectRef();
        $formRef = Generators::formRef($projectRef);
        $inputRef = Generators::inputRef($formRef);

        return [
            'id' => $projectRef,
            'type' => 'project',
            'project' => [
                'ref' => $projectRef,
                'name' => 'EC5 ValidImport Test',
                'slug' => 'ec5-validimport-test',
                'access' => 'public',
                'status' => 'active',
                'category' => 'general',
                'visibility' => 'hidden',
                'description' => 'A minimal project for schema-validation tests.',
                'small_description' => 'Minimal test project.',
                'homepage' => config('app.url') . '/project/ec5-validimport-test',
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'entries_limits' => [],
                'can_bulk_upload' => 'nobody',
                'forms' => [
                    [
                        'ref' => $formRef,
                        'name' => 'Form One',
                        'slug' => 'form-one',
                        'type' => 'hierarchy',
                        'inputs' => [
                            [
                                'ref' => $inputRef,
                                'type' => 'text',
                                'question' => 'What is your name?',
                                'is_title' => true,
                                'is_required' => false,
                                'uniqueness' => 'none',
                                'verify' => false,
                                'jumps' => [],
                                'possible_answers' => [],
                                'branch' => [],
                                'group' => [],
                                'regex' => null,
                                'default' => '',
                                'max' => null,
                                'min' => null,
                                'datetime_format' => null,
                                'set_to_current_datetime' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function minimalValidMappingPayload(array $projectPayload, string $mappingName = 'Imported Mapping'): array
    {
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputRef = $projectPayload['project']['forms'][0]['inputs'][0]['ref'];

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
        $response = $this->postValidateImport([], null);

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
        $response = $this->postValidateImport([], 'wrong-token');

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
    // ImportJsonValidator (basic structure) errors — all surfaced as ec5 codes
    // -------------------------------------------------------------------------

    public function test_rejects_payload_missing_data_key(): void
    {
        $response = $this->postValidateImport(['foo' => 'bar']);

        $this->assertEc5Code($response, 'ec5_269');
    }

    public function test_rejects_payload_with_missing_data_type(): void
    {
        $projectDefinition = ProjectDefinitionGeneratorStub::create();
        unset($projectDefinition['data']['type']);

        $response = $this->postValidateImport($projectDefinition);

        $this->assertEc5Code($response, 'ec5_281');
    }

    public function test_rejects_payload_missing_project(): void
    {
        $payload = $this->minimalValidPayload();
        unset($payload['project']);

        $this->assertEc5Code($this->postValidateImport(['data' => $payload]), 'ec5_353');
    }

    public function test_rejects_payload_with_project_not_array(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['project'] = 'not-an-array';

        $this->assertEc5Code($this->postValidateImport(['data' => $payload]), 'ec5_268');
    }

    public function test_rejects_payload_missing_id(): void
    {
        $payload = $this->minimalValidPayload();
        unset($payload['id']);

        $this->assertEc5Code($this->postValidateImport(['data' => $payload]), 'ec5_289');
    }

    // -------------------------------------------------------------------------
    // JSON Schema validation (ProjectSchemaValidator) errors — schema violations
    // -------------------------------------------------------------------------

    public function test_rejects_payload_with_schema_violation(): void
    {
        $projectDefinition = ProjectDefinitionGeneratorStub::create();
        $projectDefinition['data']['project']['category'] = 'invalid-category-xyz';

        $response = $this->postValidateImport($projectDefinition);

        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
        foreach ($errors as $error) {
            $this->assertEquals('project-json-validator', $error['source']);
            $this->assertNotEmpty($error['schema']);
            $this->assertNotEmpty($error['title']);
        }
    }

    public function test_rejects_project_ref_with_invalid_pattern(): void
    {
        $payload = $this->minimalValidPayload();
        $payload['project']['ref'] = 'not-a-valid-ref';

        $this->assertSchemaViolation($this->postValidateImport(['data' => $payload]), '/data/project/ref');
    }

    public function test_rejects_input_ref_with_invalid_pattern(): void
    {
        $payload = $this->payloadWithInputs([
            $this->makeInput('text', 'invalid_input_ref', $this->minimalValidPayload()['project']['forms'][0]['ref']),
        ]);

        $this->assertSchemaViolation($this->postValidateImport($payload), '/data/project/forms');
    }

    public function test_rejects_less_than_chars_in_question(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $payload = $this->payloadWithInputs([
            $this->makeInput('text', Generators::inputRef($formRef), $formRef, ['question' => '<foo>']),
        ], [], $projectPayload);

        $this->assertSchemaViolation($this->postValidateImport($payload));
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_accepts_valid_payload_and_returns_schema_success(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $projectRef = $projectPayload['project']['ref'];
        $response = $this->postValidateImport(['data' => $projectPayload]);

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
        // The response echoes back the payload's own ref, not a freshly generated one.
        $this->assertEquals($projectRef, $data['id']);
        $this->assertNotEmpty($data['schema']);
        $this->assertNotEmpty($data['validated_at']);
        $this->assertNotEmpty($data['project']['name']);
        $this->assertNotEmpty($data['project']['slug']);
    }

    public function test_accepts_valid_payload_with_custom_project_mapping(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $mapping = $this->minimalValidMappingPayload($projectPayload, 'Imported Mapping');
        $response = $this->postValidateImport($this->wrapWithMapping(['data' => $projectPayload], $mapping));

        $this->assertEquals('passed', $response->json('data.validation'));
    }

    public function test_accepts_valid_payload_with_ec5_auto_project_mapping(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $mapping = $this->minimalValidMappingPayload($projectPayload, 'EC5_AUTO');
        $response = $this->postValidateImport($this->wrapWithMapping(['data' => $projectPayload], $mapping));

        $this->assertEquals('passed', $response->json('data.validation'));
    }

    // -------------------------------------------------------------------------
    // Mapping validation — only existence/uniqueness errors reach the app layer
    // (the JSON Schema validates mapping key patterns first).
    // -------------------------------------------------------------------------

    public function test_rejects_mapping_with_unknown_form_ref(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputRef = $projectPayload['project']['forms'][0]['inputs'][0]['ref'];
        // A pattern-valid form ref (32 hex + "_" + 13 hex) that does not exist in the project.
        $unknownFormRef = Generators::formRef(Generators::projectRef());

        $mapping = [
            'name' => 'Imported Mapping',
            'forms' => [
                $unknownFormRef => [
                    $inputRef => ['hide' => false, 'group' => [], 'branch' => [], 'map_to' => 'x', 'possible_answers' => []]
                ]
            ],
            'map_index' => 0,
            'is_default' => true
        ];

        $this->assertEc5Code($this->postValidateImport($this->wrapWithMapping(['data' => $projectPayload], $mapping)), 'ec5_15');
    }

    public function test_rejects_mapping_with_unknown_input_ref(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputRef = $projectPayload['project']['forms'][0]['inputs'][0]['ref'];
        // A pattern-valid input ref from a *different* form: passes the schema
        // but does not exist in this project -> app-layer ec5_84.
        $unknownInputRef = Generators::inputRef(Generators::formRef(Generators::projectRef()));

        $mapping = [
            'name' => 'Imported Mapping',
            'forms' => [
                $formRef => [
                    $unknownInputRef => ['hide' => false, 'group' => [], 'branch' => [], 'map_to' => 'x', 'possible_answers' => []]
                ]
            ],
            'map_index' => 0,
            'is_default' => true
        ];

        $this->assertEc5Code($this->postValidateImport($this->wrapWithMapping(['data' => $projectPayload], $mapping)), 'ec5_84');
    }

    public function test_rejects_mapping_with_unknown_possible_answer_ref(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputRef = $projectPayload['project']['forms'][0]['inputs'][0]['ref'];

        $mapping = [
            'name' => 'Imported Mapping',
            'forms' => [
                $formRef => [
                    $inputRef => [
                        'hide' => false,
                        'group' => [],
                        'branch' => [],
                        'map_to' => 'x',
                        'possible_answers' => [
                            self::ANSWER_REF => ['map_to' => 'y']
                        ]
                    ]
                ]
            ],
            'map_index' => 0,
            'is_default' => true
        ];

        $this->assertEc5Code($this->postValidateImport($this->wrapWithMapping(['data' => $projectPayload], $mapping)), 'ec5_25');
    }

    public function test_rejects_mapping_with_invalid_possible_answer_mapping(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputRef = $projectPayload['project']['forms'][0]['inputs'][0]['ref'];

        $mapping = [
            'name' => 'Imported Mapping',
            'forms' => [
                $formRef => [
                    $inputRef => [
                        'hide' => false,
                        'group' => [],
                        'branch' => [],
                        'map_to' => 'x',
                        'possible_answers' => [
                            self::ANSWER_REF => ['map_to' => 'has < angle bracket']
                        ]
                    ]
                ]
            ],
            'map_index' => 0,
            'is_default' => true
        ];

        $this->assertEc5Code($this->postValidateImport($this->wrapWithMapping(['data' => $projectPayload], $mapping)), 'ec5_25');
    }

    public function test_rejects_mapping_of_excluded_readme_type(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $readmeRef = Generators::inputRef($formRef);
        // Add a readme input to the project, then map it (readme is excluded from mapping).
        $readmeInput = $this->makeInput('readme', $readmeRef, $formRef);
        $payload = $this->payloadWithInputs([
            $this->makeInput('text', Generators::inputRef($formRef), $formRef),
            $readmeInput,
        ], [], $projectPayload);

        $mapping = [
            'name' => 'Imported Mapping',
            'forms' => [
                $formRef => [
                    $readmeRef => ['hide' => false, 'group' => [], 'branch' => [], 'map_to' => 'x', 'possible_answers' => []]
                ]
            ],
            'map_index' => 0,
            'is_default' => true
        ];

        $this->assertEc5Code($this->postValidateImport($this->wrapWithMapping($payload, $mapping)), 'ec5_29');
    }

    // -------------------------------------------------------------------------
    // Definition (app-layer) validation — codes the JSON Schema does not pre-empt
    // -------------------------------------------------------------------------

    public function test_rejects_duplicate_form_ref(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputRef = $projectPayload['project']['forms'][0]['inputs'][0]['ref'];
        $form = $projectPayload['project']['forms'][0];
        $form['inputs'] = [$this->makeInput('text', $inputRef, $formRef)];
        // Two forms sharing the same ref but with different names, so the ref
        // collision (ec5_224) is what surfaces rather than the name collision.
        $formB = $form;
        $formB['name'] = 'Other Form';
        $projectPayload['project']['forms'] = [$form, $formB];

        $this->assertEc5Code($this->postValidateImport(['data' => $projectPayload]), 'ec5_224');
    }

    public function test_rejects_duplicate_form_name(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputRef = $projectPayload['project']['forms'][0]['inputs'][0]['ref'];
        $formA = $projectPayload['project']['forms'][0];
        $formA['inputs'] = [$this->makeInput('text', $inputRef, $formRef)];
        $formB = $formA;
        $formB['ref'] = Generators::formRef(Generators::projectRef());
        $formB['inputs'] = [$this->makeInput('text', Generators::inputRef($formB['ref']), $formB['ref'])];

        $projectPayload['project']['forms'] = [$formA, $formB];

        $this->assertEc5Code($this->postValidateImport(['data' => $projectPayload]), 'ec5_245');
    }

    public function test_rejects_too_many_forms(): void
    {
        $max = config('epicollect.limits.formsMaxCount');
        $projectPayload = $this->minimalValidPayload();
        $baseForm = $projectPayload['project']['forms'][0];
        $forms = [];
        for ($i = 0; $i <= $max; $i++) {
            $formRef = Generators::formRef(Generators::projectRef());
            $inputRef = Generators::inputRef($formRef);
            $form = $baseForm;
            $form['ref'] = $formRef;
            $form['name'] = 'Form ' . $i;
            $form['slug'] = 'form-' . $i;
            $form['inputs'] = [$this->makeInput('text', $inputRef, $formRef)];
            $forms[] = $form;
        }
        $projectPayload['project']['forms'] = $forms;

        // The JSON Schema caps forms at the same limit (formsMaxCount = 5) and
        // rejects the 6th form before the app-layer ec5_263 count check runs, so
        // the endpoint surfaces a schema violation. The app-layer ec5_263 path is
        // covered directly in MaxFormsTest.
        $this->assertSchemaViolation($this->postValidateImport(['data' => $projectPayload]), '/data/project/forms');
    }

    public function test_rejects_too_many_search_inputs(): void
    {
        $max = config('epicollect.limits.searchMaxCount');
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputs = [];
        for ($i = 0; $i <= $max; $i++) {
            $inputs[] = $this->makeInput('searchsingle', Generators::inputRef($formRef), $formRef);
        }

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs($inputs, [], $projectPayload)), 'ec5_333');
    }

    public function test_rejects_too_many_titles(): void
    {
        $max = config('epicollect.limits.titlesMaxCount');
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputs = [];
        for ($i = 0; $i <= $max; $i++) {
            $inputs[] = $this->makeInput('text', Generators::inputRef($formRef), $formRef, ['is_title' => true]);
        }

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs($inputs, [], $projectPayload)), 'ec5_211');
    }

    public function test_rejects_too_many_inputs_recursive(): void
    {
        $max = config('epicollect.limits.inputsMaxCount');
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputs = [];
        // Stay within the top-level maxItems (schema) but exceed the recursive count.
        for ($i = 0; $i < $max - 1; $i++) {
            $inputs[] = $this->makeInput('text', Generators::inputRef($formRef), $formRef);
        }
        // One group holding enough nested inputs to push the recursive total over the limit.
        $groupRef = Generators::inputRef($formRef);
        $groupChildren = [];
        for ($j = 0; $j < 3; $j++) {
            $groupChildren[] = $this->makeInput('text', $this->ref($groupRef), $formRef);
        }
        $inputs[] = $this->makeInput('group', $groupRef, $formRef, ['group' => $groupChildren]);

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs($inputs, [], $projectPayload)), 'ec5_262');
    }

    public function test_rejects_invalid_project_details_slug(): void
    {
        // 'slug' => 'not_in:create' is an app-layer rule the JSON Schema does not
        // enforce, so the project-details validator surfaces ec5_63.
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $payload = $this->payloadWithInputs(
            [$this->makeInput('text', Generators::inputRef($formRef), $formRef)],
            ['slug' => 'create'],
            $projectPayload
        );

        $this->assertEc5Code($this->postValidateImport($payload), 'ec5_63');
    }

    // -------------------------------------------------------------------------
    // Per-input-type validation
    // -------------------------------------------------------------------------

    public function test_rejects_integer_invalid_default(): void
    {
        // A non-numeric default fails the numeric range check (ec5_28); the
        // dedicated ec5_339 (default-not-in-possible-answers) only applies to
        // choice inputs, not integer/decimal.
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $input = $this->makeInput('integer', Generators::inputRef($formRef), $formRef, ['default' => 'not-a-number']);

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs([$input], [], $projectPayload)), 'ec5_28');
    }

    public function test_rejects_decimal_invalid_default(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $input = $this->makeInput('decimal', Generators::inputRef($formRef), $formRef, ['default' => 'abc']);

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs([$input], [], $projectPayload)), 'ec5_28');
    }

    public function test_rejects_numeric_default_out_of_range(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $input = $this->makeInput('integer', Generators::inputRef($formRef), $formRef, [
            'default' => '999999999999',
            'min' => '0',
            'max' => '100',
        ]);

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs([$input], [], $projectPayload)), 'ec5_28');
    }

    public function test_rejects_choice_default_not_in_possible_answers(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        // A valid 13-hex answer_ref pattern that is NOT one of the possible answers.
        $input = $this->makeInput('dropdown', Generators::inputRef($formRef), $formRef, ['default' => 'b5f9b3c2d1e6f']);

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs([$input], [], $projectPayload)), 'ec5_339');
    }

    public function test_rejects_invalid_jump_for_non_choice_input(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        // A choice input whose jump uses a non-ALL `when` with an empty answer_ref.
        // Sanitisation strips `to:END` jumps only on the terminal input, so a
        // trailing input is added to keep the jump in place for the app validator.
        $dropdownRef = Generators::inputRef($formRef);
        $trailing = $this->makeInput('text', Generators::inputRef($formRef), $formRef);
        $input = $this->makeInput('dropdown', $dropdownRef, $formRef, [
            'jumps' => [['to' => 'END', 'when' => 'IS', 'answer_ref' => null]],
        ]);

        // At the endpoint the JSON Schema forbids a non-ALL `when` on non-choice
        // inputs, so the choice-input case is used here; the non-choice ec5_207
        // branch is covered directly by the RuleInput media tests (Video/Photo/Audio).
        $this->assertEc5Code(
            $this->postValidateImport($this->payloadWithInputs([$input, $trailing], [], $projectPayload)),
            'ec5_207'
        );
    }

    public function test_rejects_choice_jump_with_unknown_answer_ref(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        // A valid 13-hex answer_ref pattern that is NOT one of the possible answers.
        // A trailing input keeps the jump from being stripped by sanitisation.
        $dropdownRef = Generators::inputRef($formRef);
        $trailing = $this->makeInput('text', Generators::inputRef($formRef), $formRef);
        $input = $this->makeInput('dropdown', $dropdownRef, $formRef, [
            'jumps' => [['to' => 'END', 'when' => 'IS', 'answer_ref' => 'b5f9b3c2d1e6f']],
        ]);

        $this->assertEc5Code(
            $this->postValidateImport($this->payloadWithInputs([$input, $trailing], [], $projectPayload)),
            'ec5_265'
        );
    }

    public function test_rejects_readme_question_too_long(): void
    {
        $limit = config('epicollect.limits.readme_question_limit');
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        // Schema does not length-limit readme questions, so this is app-layer.
        $input = $this->makeInput('readme', Generators::inputRef($formRef), $formRef, [
            'question' => str_repeat('a', $limit + 1),
        ]);

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs([$input], [], $projectPayload)), 'ec5_244');
    }

    public function test_rejects_date_invalid_datetime_format(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $input = $this->makeInput('date', Generators::inputRef($formRef), $formRef, ['datetime_format' => 'NOT_A_FORMAT']);

        $this->assertSchemaViolation($this->postValidateImport($this->payloadWithInputs([$input], [], $projectPayload)));
    }

    public function test_rejects_photo_marked_required(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $input = $this->makeInput('photo', Generators::inputRef($formRef), $formRef, ['is_required' => true]);

        $this->assertSchemaViolation($this->postValidateImport($this->payloadWithInputs([$input], [], $projectPayload)));
    }

    public function test_rejects_choice_with_empty_possible_answers(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $input = $this->makeInput('dropdown', Generators::inputRef($formRef), $formRef, ['possible_answers' => []]);

        $this->assertSchemaViolation($this->postValidateImport($this->payloadWithInputs([$input], [], $projectPayload)));
    }

    // -------------------------------------------------------------------------
    // Nested structures
    // -------------------------------------------------------------------------

    public function test_rejects_group_input_with_jump(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $groupInputRef = Generators::inputRef($formRef);
        $groupChildRef = $this->ref($groupInputRef);
        $groupChild = $this->makeInput('text', $groupChildRef, $formRef, [
            'jumps' => [['to' => 'END', 'when' => 'ALL', 'answer_ref' => null]],
        ]);
        $group = $this->makeInput('group', $groupInputRef, $formRef, ['group' => [$groupChild]]);

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs([$group], [], $projectPayload)), 'ec5_320');
    }

    public function test_rejects_invalid_jump_destination(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        // A valid input-ref pattern that does not exist in the form: the schema
        // passes, but RuleForm rejects it as an invalid jump destination.
        $nonexistentRef = $formRef . '_b5f9b3c2d1e6f';
        $input = $this->makeInput('text', Generators::inputRef($formRef), $formRef, [
            'jumps' => [['to' => $nonexistentRef, 'when' => 'ALL', 'answer_ref' => null]],
        ]);

        $this->assertEc5Code($this->postValidateImport($this->payloadWithInputs([$input], [], $projectPayload)), 'ec5_264');
    }

    public function test_accepts_valid_nested_group_and_branch(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $groupInputRef = Generators::inputRef($formRef);
        $branchInputRef = $formRef . '_b5f9b3c2d1e6f';
        $group = $this->makeInput('group', $groupInputRef, $formRef, [
            'group' => [$this->makeInput('text', $this->ref($groupInputRef), $formRef)],
        ]);
        $branch = $this->makeInput('branch', $branchInputRef, $formRef, [
            'branch' => [$this->makeInput('text', $this->ref($branchInputRef), $formRef)],
        ]);

        $response = $this->postValidateImport($this->payloadWithInputs([$group, $branch], [], $projectPayload));
        $this->assertEquals('passed', $response->json('data.validation'));
    }
}

/**
 * Small stub standing in for ProjectDefinitionGenerator so the structure-level
 * tests do not depend on random generator output. Mirrors the shape produced
 * by ProjectDefinitionGenerator::createProject(1).
 */
class ProjectDefinitionGeneratorStub
{
    public static function create(): array
    {
        $projectRef = Generators::projectRef();
        $formRef = Generators::formRef($projectRef);
        $inputRef = Generators::inputRef($formRef);

        return [
            'data' => [
                'id' => $projectRef,
                'type' => 'project',
                'project' => [
                    'ref' => $projectRef,
                    'name' => 'EC5 ValidImport Test',
                    'slug' => 'ec5-validimport-test',
                    'access' => 'public',
                    'status' => 'active',
                    'category' => 'general',
                    'visibility' => 'hidden',
                    'description' => 'A minimal project for schema-validation tests.',
                    'small_description' => 'Minimal test project.',
                    'homepage' => config('app.url') . '/project/ec5-validimport-test',
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'entries_limits' => [],
                    'can_bulk_upload' => 'nobody',
                    'forms' => [
                        [
                            'ref' => $formRef,
                            'name' => 'Form One',
                            'slug' => 'form-one',
                            'type' => 'hierarchy',
                            'inputs' => [
                                [
                                    'ref' => $inputRef,
                                    'type' => 'text',
                                    'question' => 'What is your name?',
                                    'is_title' => true,
                                    'is_required' => false,
                                    'uniqueness' => 'none',
                                    'verify' => false,
                                    'jumps' => [],
                                    'possible_answers' => [],
                                    'branch' => [],
                                    'group' => [],
                                    'regex' => null,
                                    'default' => '',
                                    'max' => null,
                                    'min' => null,
                                    'datetime_format' => null,
                                    'set_to_current_datetime' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        ];
    }
}
