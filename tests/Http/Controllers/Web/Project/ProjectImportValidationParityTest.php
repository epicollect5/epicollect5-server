<?php

namespace Tests\Http\Controllers\Web\Project;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Parity coverage for the project IMPORT path (web `myprojects/import`,
 * ProjectImportController -> ProjectDTO::import()).
 *
 * Unlike the import-validation API endpoint, the import path has NO JSON
 * Schema gate: RuleImportJson only checks the basic envelope (data.id/type/
 * project) and then ProjectDTO::import() runs the *same* app-layer validators
 * (RuleProjectDefinition / RuleImportProjectMapping / RuleInput) as the
 * validation path. As a result:
 *   - app-layer ec5_* codes are reachable here even when the validation path's
 *     schema pre-empts them (e.g. ec5_263 too many forms);
 *   - schema-only constraints surface as app-layer codes instead (e.g. an
 *     invalid input ref becomes ec5_243 rather than a schema violation).
 *
 * These tests assert the same ec5_* codes the validation path produces, so the
 * two flows can be consolidated on the schema validator later without losing
 * behavioural coverage. (The project ref is regenerated on import, so the
 * echoed id differs — that is the only intended divergence.)
 */
class ProjectImportValidationParityTest extends TestCase
{
    use DatabaseTransactions;

    private const string ROUTE = 'myprojects/import';
    private const string ANSWER_REF = 'a4f9b3c2d1e6f';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::where('email', config('testing.SUPER_ADMIN_EMAIL'))->first();
        $this->be($this->user, 'web');
    }

    // -------------------------------------------------------------------------
    // Payload helpers (mirror ProjectValidateImportControllerTest)
    // -------------------------------------------------------------------------

    private function ref(string $scope): string
    {
        return $scope . '_' . uniqid();
    }

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
                'name' => 'EC5 Import Parity Test',
                'slug' => 'ec5-import-parity-test',
                'access' => 'public',
                'status' => 'active',
                'category' => 'general',
                'visibility' => 'hidden',
                'description' => 'A minimal project for import-validation tests.',
                'small_description' => 'Minimal test project.',
                'homepage' => config('app.url') . '/project/ec5-import-parity-test',
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

    // -------------------------------------------------------------------------
    // Import-specific harness
    // -------------------------------------------------------------------------

    private function postImport(array $payload, string $name = 'Import Parity Test Project'): TestResponse
    {
        $fileContent = json_encode($payload);
        $tempFile = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($tempFile, $fileContent);
        $fakeFile = UploadedFile::fake()->create('project.json', 512, 'application/json');
        copy($tempFile, $fakeFile->getRealPath());
        unlink($tempFile);

        return $this->post(self::ROUTE, [
            'name' => $name,
            'file' => $fakeFile,
        ]);
    }

    private function assertImportEc5Code(array $payload, string $code, string $name = 'Import Parity Test Project'): void
    {
        $response = $this->postImport($payload, $name);
        $response->assertRedirect('myprojects/create');

        $bag = $this->app['session']->get('errors');
        $messages = $bag ? $bag->all() : [];
        $this->assertContains($code, $messages, "Expected ec5 code $code in import errors. Got: " . json_encode($messages));
    }

    // -------------------------------------------------------------------------
    // App-layer parity (same ec5_* codes as the validation path)
    // -------------------------------------------------------------------------

    public function test_import_rejects_too_many_forms(): void
    {
        // No schema gate on import, so the app-layer ec5_263 is reached here
        // (whereas the validation path's schema pre-empts it).
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

        $this->assertImportEc5Code(['data' => $projectPayload], 'ec5_263');
    }

    public function test_import_rejects_too_many_search_inputs(): void
    {
        $max = config('epicollect.limits.searchMaxCount');
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputs = [];
        for ($i = 0; $i <= $max; $i++) {
            $inputs[] = $this->makeInput('searchsingle', Generators::inputRef($formRef), $formRef);
        }

        $this->assertImportEc5Code($this->payloadWithInputs($inputs, [], $projectPayload), 'ec5_333');
    }

    public function test_import_rejects_too_many_titles(): void
    {
        $max = config('epicollect.limits.titlesMaxCount');
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputs = [];
        for ($i = 0; $i <= $max; $i++) {
            $inputs[] = $this->makeInput('text', Generators::inputRef($formRef), $formRef, ['is_title' => true]);
        }

        $this->assertImportEc5Code($this->payloadWithInputs($inputs, [], $projectPayload), 'ec5_211');
    }

    public function test_import_rejects_too_many_inputs_recursive(): void
    {
        $max = config('epicollect.limits.inputsMaxCount');
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputs = [];
        for ($i = 0; $i < $max - 1; $i++) {
            $inputs[] = $this->makeInput('text', Generators::inputRef($formRef), $formRef);
        }
        $groupRef = Generators::inputRef($formRef);
        $groupChildren = [];
        for ($j = 0; $j < 3; $j++) {
            $groupChildren[] = $this->makeInput('text', $this->ref($groupRef), $formRef);
        }
        $inputs[] = $this->makeInput('group', $groupRef, $formRef, ['group' => $groupChildren]);

        $this->assertImportEc5Code($this->payloadWithInputs($inputs, [], $projectPayload), 'ec5_262');
    }

    public function test_import_rejects_duplicate_form_ref(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $inputRef = $projectPayload['project']['forms'][0]['inputs'][0]['ref'];
        $form = $projectPayload['project']['forms'][0];
        $form['inputs'] = [$this->makeInput('text', $inputRef, $formRef)];
        $formB = $form;
        $formB['name'] = 'Other Form';
        $projectPayload['project']['forms'] = [$form, $formB];

        $this->assertImportEc5Code(['data' => $projectPayload], 'ec5_224');
    }

    public function test_import_rejects_duplicate_form_name(): void
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

        $this->assertImportEc5Code(['data' => $projectPayload], 'ec5_245');
    }

    // NOTE: ec5_63 (invalid project slug) is intentionally NOT covered here. On
    // the import path the slug is derived from the POST name via Str::slug() and
    // is never validated from the payload, so the code is unreachable. The
    // validation path still covers it directly.

    public function test_import_rejects_integer_invalid_default(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $input = $this->makeInput('integer', Generators::inputRef($formRef), $formRef, ['default' => 'not-a-number']);

        $this->assertImportEc5Code($this->payloadWithInputs([$input], [], $projectPayload), 'ec5_28');
    }

    public function test_import_rejects_choice_default_not_in_possible_answers(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $input = $this->makeInput('dropdown', Generators::inputRef($formRef), $formRef, ['default' => 'b5f9b3c2d1e6f']);

        $this->assertImportEc5Code($this->payloadWithInputs([$input], [], $projectPayload), 'ec5_339');
    }

    public function test_import_rejects_invalid_jump_for_non_choice_input(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $dropdownRef = Generators::inputRef($formRef);
        $trailing = $this->makeInput('text', Generators::inputRef($formRef), $formRef);
        $input = $this->makeInput('dropdown', $dropdownRef, $formRef, [
            'jumps' => [['to' => 'END', 'when' => 'IS', 'answer_ref' => null]],
        ]);

        $this->assertImportEc5Code($this->payloadWithInputs([$input, $trailing], [], $projectPayload), 'ec5_207');
    }

    public function test_import_rejects_choice_jump_with_unknown_answer_ref(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $dropdownRef = Generators::inputRef($formRef);
        $trailing = $this->makeInput('text', Generators::inputRef($formRef), $formRef);
        $input = $this->makeInput('dropdown', $dropdownRef, $formRef, [
            'jumps' => [['to' => 'END', 'when' => 'IS', 'answer_ref' => 'b5f9b3c2d1e6f']],
        ]);

        $this->assertImportEc5Code($this->payloadWithInputs([$input, $trailing], [], $projectPayload), 'ec5_265');
    }

    public function test_import_rejects_group_input_with_jump(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $groupInputRef = Generators::inputRef($formRef);
        $groupChildRef = $this->ref($groupInputRef);
        $groupChild = $this->makeInput('text', $groupChildRef, $formRef, [
            'jumps' => [['to' => 'END', 'when' => 'ALL', 'answer_ref' => null]],
        ]);
        $group = $this->makeInput('group', $groupInputRef, $formRef, ['group' => [$groupChild]]);

        $this->assertImportEc5Code($this->payloadWithInputs([$group], [], $projectPayload), 'ec5_320');
    }

    public function test_import_rejects_invalid_jump_destination(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $nonexistentRef = $formRef . '_b5f9b3c2d1e6f';
        $input = $this->makeInput('text', Generators::inputRef($formRef), $formRef, [
            'jumps' => [['to' => $nonexistentRef, 'when' => 'ALL', 'answer_ref' => null]],
        ]);

        $this->assertImportEc5Code($this->payloadWithInputs([$input], [], $projectPayload), 'ec5_264');
    }

    public function test_import_rejects_readme_question_too_long(): void
    {
        $limit = config('epicollect.limits.readme_question_limit');
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $input = $this->makeInput('readme', Generators::inputRef($formRef), $formRef, [
            'question' => str_repeat('a', $limit + 1),
        ]);

        $this->assertImportEc5Code($this->payloadWithInputs([$input], [], $projectPayload), 'ec5_244');
    }

    // -------------------------------------------------------------------------
    // Mapping parity
    // -------------------------------------------------------------------------

    public function test_import_rejects_mapping_unknown_form_ref(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $inputRef = $projectPayload['project']['forms'][0]['inputs'][0]['ref'];
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

        $this->assertImportEc5Code($this->wrapWithMapping(['data' => $projectPayload], $mapping), 'ec5_15');
    }

    public function test_import_rejects_mapping_unknown_input_ref(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
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

        $this->assertImportEc5Code($this->wrapWithMapping(['data' => $projectPayload], $mapping), 'ec5_84');
    }

    public function test_import_rejects_mapping_with_invalid_possible_answer_mapping(): void
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

        $this->assertImportEc5Code($this->wrapWithMapping(['data' => $projectPayload], $mapping), 'ec5_25');
    }

    public function test_import_rejects_mapping_of_excluded_readme_type(): void
    {
        $projectPayload = $this->minimalValidPayload();
        $formRef = $projectPayload['project']['forms'][0]['ref'];
        $readmeRef = Generators::inputRef($formRef);
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

        $this->assertImportEc5Code($this->wrapWithMapping($payload, $mapping), 'ec5_29');
    }

    // -------------------------------------------------------------------------
    // Happy paths
    // -------------------------------------------------------------------------

    public function test_import_accepts_valid_payload(): void
    {
        $response = $this->postImport(['data' => $this->minimalValidPayload()]);

        $response->assertRedirect('myprojects/import-parity-test-project');
        $this->assertDatabaseHas('projects', ['slug' => 'import-parity-test-project']);
    }

    public function test_import_accepts_valid_nested_group_and_branch(): void
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

        $response = $this->postImport($this->payloadWithInputs([$group, $branch], [], $projectPayload));

        $response->assertRedirect('myprojects/import-parity-test-project');
    }
}
