<?php

namespace Tests\Generators;

use Carbon\Carbon;
use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDefinitionDTO;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectExtraDTO;
use ec5\DTO\ProjectMappingDTO;
use ec5\DTO\ProjectRoleDTO;
use ec5\DTO\ProjectStatsDTO;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Services\Entries\CreateEntryService;
use ec5\Services\Mapping\ProjectMappingService;
use ec5\Traits\Assertions;
use Faker\Factory as Faker;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class EntryGenerator
{
    use Assertions;

    const SYMBOLS = ['!', '@', '€', '£', '#', '$', '%', '^', '&', '*', '"', '\'', '\\', '{', ',', '.', '?', '|', '<', '>', '~', '`'];
    const LOCALES = ['it_IT', 'en_US', 'de_DE', 'es_ES', 'en_GB', 'fr_FR'];

    private $faker;
    private $randomLocale;
    private $projectDefinition;
    private $multipleChoiceQuestionTypes;
    private $multipleChoiceInputRefs;

    public function __construct(array $projectDefinition)
    {
        // Pick a random locale from the array
        $this->randomLocale = self::LOCALES[array_rand(self::LOCALES)];
        // Set Faker to use the random locale
        $this->faker = Faker::create($this->randomLocale);
        $this->projectDefinition = $projectDefinition;
        $this->multipleChoiceQuestionTypes = array_keys(config('epicollect.strings.multiple_choice_question_types'));

    }

    private function createAnswer($input, $uuid): array
    {
        switch ($input['type']) {
            case 'text':
            case 'textarea':
                $maxlength = $input['type'] === 'text' ? 255 : 1000;
                if (!empty($input['regex'])) {
                    $randomPhrase = $this->generateStringFromRegex($input['regex']);
                } else {
                    $numberOfWords = mt_rand(1, 10);
                    $randomPhrase = '';
                    for ($i = 0; $i < $numberOfWords; $i++) {
                        if ($this->randomLocale !== 'de_DE') {
                            $randomPhrase .= ' ' . $this->faker->catchPhrase;
                        } else {
                            $randomPhrase .= ' ' . $this->faker->sentence;
                        }

                    }
                    $randomPhrase .= ' ' . self::SYMBOLS[mt_rand(0, count(self::SYMBOLS) - 1)];
                    $randomPhrase = str_replace('>', '\ufe65', $randomPhrase);
                    $randomPhrase = str_replace('<', '\ufe64', $randomPhrase);
                }
                $answer = [
                    'answer' => Str::limit(trim($randomPhrase), $maxlength - 3),//consider ellipsis
                    'was_jumped' => false
                ];
                break;
            case 'integer':
                if ($input['min'] && $input['max']) {
                    $answer = $this->faker->numberBetween($input['min'], $input['max']);
                } elseif ($input['min']) {
                    $answer = $this->faker->numberBetween($input['min'], $input['min'] + 1000);
                } elseif ($input['max']) {
                    $answer = $this->faker->numberBetween($input['max'] - 1000, $input['max']);
                } elseif ($input['regex']) {
                    $answer = (int)$this->generateStringFromRegex($input['regex']);
                } else {
                    $answer = $this->faker->randomNumber();
                }
                $answer = [
                    'answer' => $answer ?? '',
                    'was_jumped' => false
                ];
                break;
            case 'phone':
                if ($input['min'] && $input['max']) {
                    $answer = $this->faker->numberBetween($input['min'], $input['max']);
                } elseif ($input['min']) {
                    $answer = $this->faker->numberBetween($input['min'], $input['min'] + 1000);
                } elseif ($input['max']) {
                    $answer = $this->faker->numberBetween($input['max'] - 1000, $input['max']);
                } elseif ($input['regex']) {
                    $answer = $this->generateStringFromRegex($input['regex']);
                } else {
                    $answer = $this->faker->randomDigitNotNull;
                }

                $answer = [
                    'answer' => $answer ?? '',
                    'was_jumped' => false
                ];
                break;
            case 'decimal':
                if ($input['min'] && $input['max']) {
                    $answer = $this->faker->numberBetween($input['min'], $input['max']);
                } elseif ($input['min']) {
                    $answer = $input['min'];
                } elseif ($input['max']) {
                    $answer = $input['max'];
                } else {
                    $answer = $this->faker->randomFloat(6);
                }
                $answer = [
                    'answer' => $answer ?? '',
                    'was_jumped' => false
                ];
                break;
            case 'radio':
            case 'dropdown':
                $randomKey = array_rand($input['possible_answers']);
                $randomAnswerRef = $input['possible_answers'][$randomKey]['answer_ref'];
                $answer = [
                    'answer' => $randomAnswerRef,
                    'was_jumped' => false
                ];
                break;
            case 'checkbox':
            case 'searchsingle':
            case 'searchmultiple':
                // Select number of random elements to pick
                $possibleAnswers = $input['possible_answers'];
                $numberOfRandomElements = rand(1, sizeof($possibleAnswers));


                $randomAnswerRefs = [];
                $keyIndexes = array_rand($possibleAnswers, $numberOfRandomElements);
                // If $keyIndexes is not an array, convert it into one for consistency
                if (!is_array($keyIndexes)) {
                    $keyIndexes = [$keyIndexes];
                }
                foreach ($keyIndexes as $keyIndex) {
                    $randomAnswerRefs[] = $possibleAnswers[$keyIndex]['answer_ref'];
                }

                //searchsingle can have only 1 answer
                if ($input['type'] === 'searchsingle') {
                    $randomAnswerRefs = array_slice($randomAnswerRefs, 0, 1);
                }

                $answer = [
                    'answer' => $randomAnswerRefs,
                    'was_jumped' => false
                ];
                break;
            case 'barcode':
                $answer = [
                    'answer' => $this->faker->creditCardNumber,
                    'was_jumped' => false
                ];
                break;
            case 'location':
                // Generate random longitude with 6 decimal digits accuracy (-180 to 180)
                $latitude = mt_rand(-90000000, 90000000) / 1000000.0;
                $longitude = mt_rand(-180000000, 180000000) / 1000000.0;
                $answer = [
                    'answer' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'accuracy' => rand(3, 50)
                    ],
                    'was_jumped' => false
                ];
                break;
            case 'date':
                $formatDate = config('epicollect.mappings.carbon_formats.fake_date');
                // Generate a random DateTime within this decade using Faker
                $randomDateTime = $this->faker->dateTimeThisDecade->format('Y-m-d H:i:s');
                // Convert the generated string to Carbon
                $carbonDate = Carbon::createFromFormat('Y-m-d H:i:s', $randomDateTime);
                // Format the Carbon date using the specified format
                $formattedDateTime = $carbonDate->format($formatDate);
                $answer = [
                    'answer' => $formattedDateTime,
                    'was_jumped' => false
                ];
                break;
            case 'time':
                $formatTime = config('epicollect.mappings.carbon_formats.fake_time');
                // Generate a random DateTime within this decade using Faker
                $randomDateTime = $this->faker->dateTimeThisDecade->format('Y-m-d H:i:s');
                // Convert the generated string to Carbon
                $carbonDate = Carbon::createFromFormat('Y-m-d H:i:s', $randomDateTime);
                // Format the Carbon date using the specified format
                $formattedDateTime = $carbonDate->format($formatTime);
                $answer = [
                    'answer' => $formattedDateTime,
                    'was_jumped' => false
                ];
                break;
            case 'photo':
                $answer = [
                    'answer' => $this->generateMediaFilename($uuid, 'photo'),
                    'was_jumped' => false
                ];
                break;
            case 'audio':
                $answer = [
                    'answer' => $this->generateMediaFilename($uuid, 'audio'),
                    'was_jumped' => false
                ];
                break;
            case 'video':
                $answer = [
                    'answer' => $this->generateMediaFilename($uuid, 'video'),
                    'was_jumped' => false
                ];
                break;
            default:
                $answer = [
                    'answer' => '',
                    'was_jumped' => false
                ];
        }

        return $answer;
    }

    public function createParentEntryPayload($formRef): array
    {
        $projectSlug = array_get($this->projectDefinition, 'data.project.slug');
        $uuid = Uuid::uuid4()->toString();
        $titles = [];

        $forms = array_get($this->projectDefinition, 'data.project.forms');
        $currentForm = '';
        foreach ($forms as $form) {
            if ($form['ref'] === $formRef) {
                $currentForm = $form;
            }
        }

        $inputs = $currentForm['inputs'];
        $answers = [];
        foreach ($inputs as $input) {

            $answers[$input['ref']] = $this->createAnswer($input, $uuid);
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                //add answers for group inputs
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    $answers[$groupInput['ref']] = $this->createAnswer($groupInput, $uuid);
                }
            }

            if ($input['is_title']) {
                $titles[] = $answers[$input['ref']]['answer'];
            }
        }

        $title = implode(' ', $titles);
        $title = $title === '' ? $uuid : $title;

        return [
            'data' => [
                'type' => 'entry',
                'id' => $uuid,
                'attributes' => [
                    'form' => [
                        'ref' => $formRef,
                        'type' => 'hierarchy'
                    ]
                ],
                'relationships' => [
                    'parent' => [
                    ],
                    'branch' => [
                    ]
                ],
                'entry' => [
                    'entry_uuid' => $uuid,
                    'created_at' => Carbon::now()->format(config('epicollect.mappings.carbon_formats.ISO')),
                    'device_id' => '',
                    'platform' => 'WEB',
                    'title' => $title,
                    'answers' => $answers,
                    'project_version' => Project::version($projectSlug)
                ]
            ]
        ];
    }

    public function createParentEntryRow($user, $project, $role, $projectDefinition, $entryPayload): array
    {
        $entryStructure = new EntryStructureDTO();
        $requestedProject = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $requestedProjectRole = new ProjectRoleDTO();
        $requestedProject->initAllDTOs(Project::findBySlug($project->slug));
        $requestedProjectRole->setRole($user, $project->id, $role);

        /* 4 - BUILD ENTRY STRUCTURE */
        $entryStructure->init(
            $entryPayload['data'],
            $project->id,
            $requestedProjectRole
        );
        //for each location question, add geoJson to entryStructure
        $inputs = array_get($projectDefinition, 'data.project.forms.0.inputs');
        $skippedInputsRefs = [];
        $this->multipleChoiceInputRefs = [];
        foreach ($inputs as $input) {
            //add the valid answers to entryStructure (we assume they are valid)
            //imp: skip group and readme
            $inputsWithoutAnswer = array_keys(config('epicollect.strings.inputs_without_answers'));
            if (!in_array($input['type'], $inputsWithoutAnswer)) {
                $answer = $entryPayload['data']['entry']['answers'][$input['ref']];
                $entryStructure->addAnswerToEntry($input, $answer);

                //need to add the possible answer, so they later added to the geojson object
                $this->addPossibleAnswers($entryStructure, $input, $answer);

                //if location, add the geojson
                $this->addGeoJsonIfNeeded($entryStructure, $input, $answer);

            }
            //if group, add answers for all the group inputs but skip the group owner
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $skippedInputsRefs[] = $input['ref'];
                //skip readme only (we cannot have nested groups)
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.readme')) {
                        $skippedInputsRefs[] = $groupInput['ref'];
                    } else {
                        $answer = $entryPayload['data']['entry']['answers'][$groupInput['ref']];
                        //add the valid answers to entryStructure (we assume they are valid)
                        $entryStructure->addAnswerToEntry($groupInput, $answer);
                        //need to add the possible answer, so they later added to the geojson object
                        $this->addPossibleAnswers($entryStructure, $groupInput, $answer);
                        //if location, add the geojson
                        $this->addGeoJsonIfNeeded($entryStructure, $groupInput, $answer);
                        if ($groupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                            $entryStructure->addGeoJsonObject($groupInput, $answer['answer']);
                            $entryStructure->addPossibleAnswersToGeoJson();
                        }
                    }
                }
            }

            if ($input['type'] === config('epicollect.strings.inputs_type.readme')) {
                $skippedInputsRefs[] = $input['ref'];
            }
        }

        $createEntryService = new CreateEntryService();
        // If we received no errors, continue to insert answers and entry
        $createEntryService->create(
            $requestedProject,
            $entryStructure);

        return [
            'projectDefinition' => $projectDefinition,
            'project' => $project,
            'entryStructure' => $entryStructure,
            'skippedInputRefs' => $skippedInputsRefs,
            'multipleChoiceInputRefs' => $this->multipleChoiceInputRefs
        ];
    }

    public function createChildEntryRow($user, $project, $role, $projectDefinition, $childEntryPayload): array
    {
        $entryStructure = new EntryStructureDTO();
        $requestedProject = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $requestedProjectRole = new ProjectRoleDTO();
        $requestedProject->initAllDTOs(Project::findBySlug($project->slug));
        $requestedProjectRole->setRole($user, $project->id, $role);

        /* 4 - BUILD ENTRY STRUCTURE */
        $entryStructure->init(
            $childEntryPayload['data'],
            $project->id,
            $requestedProjectRole
        );

        //get child form inputs
        $childFormRef = $childEntryPayload['data']['attributes']['form']['ref'];

        //for each location question, add geoJson to entryStructure
        $inputs = $requestedProject->getProjectDefinition()->getInputsByFormRef($childFormRef);
        $skippedInputsRefs = [];
        $this->multipleChoiceInputRefs = [];
        foreach ($inputs as $input) {
            //add the valid answers to entryStructure (we assume they are valid)
            //imp: skip group and readme
            $inputsWithoutAnswer = array_keys(config('epicollect.strings.inputs_without_answers'));
            if (!in_array($input['type'], $inputsWithoutAnswer)) {
                $answer = $childEntryPayload['data']['entry']['answers'][$input['ref']];
                $entryStructure->addAnswerToEntry($input, $answer);

                //need to add the possible answer, so they later added to the geojson object
                $this->addPossibleAnswers($entryStructure, $input, $answer);

                //if location, add the geojson
                $this->addGeoJsonIfNeeded($entryStructure, $input, $answer);

            }
            //if group, add answers for all the group inputs but skip the group owner
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $skippedInputsRefs[] = $input['ref'];
                //skip readme only (we cannot have nested groups)
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.readme')) {
                        $skippedInputsRefs[] = $groupInput['ref'];
                    } else {
                        $answer = $childEntryPayload['data']['entry']['answers'][$groupInput['ref']];
                        //add the valid answers to entryStructure (we assume they are valid)
                        $entryStructure->addAnswerToEntry($groupInput, $answer);
                        //need to add the possible answer, so they later added to the geojson object
                        $this->addPossibleAnswers($entryStructure, $groupInput, $answer);
                        //if location, add the geojson
                        $this->addGeoJsonIfNeeded($entryStructure, $groupInput, $answer);
                        if ($groupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                            $entryStructure->addGeoJsonObject($groupInput, $answer['answer']);
                            $entryStructure->addPossibleAnswersToGeoJson();
                        }
                    }
                }
            }

            if ($input['type'] === config('epicollect.strings.inputs_type.readme')) {
                $skippedInputsRefs[] = $input['ref'];
            }
        }

        $createEntryService = new CreateEntryService();
        // If we received no errors, continue to insert answers and entry
        $createEntryService->create(
            $requestedProject,
            $entryStructure);

        return [
            'projectDefinition' => $projectDefinition,
            'project' => $project,
            'entryStructure' => $entryStructure,
            'skippedInputRefs' => $skippedInputsRefs,
            'multipleChoiceInputRefs' => $this->multipleChoiceInputRefs
        ];
    }

    public function createChildEntryPayload($childFormRef, $parentFormRef, $parentEntryUuid): array
    {
        $projectSlug = array_get($this->projectDefinition, 'data.project.slug');
        $uuid = Uuid::uuid4()->toString();
        $titles = [];

        $forms = array_get($this->projectDefinition, 'data.project.forms');
        $currentForm = '';
        foreach ($forms as $form) {
            if ($form['ref'] === $childFormRef) {
                $currentForm = $form;
            }
        }

        $inputs = $currentForm['inputs'];
        $answers = [];
        foreach ($inputs as $input) {

            $answers[$input['ref']] = $this->createAnswer($input, $uuid);
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                //add answers for group inputs
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    $answers[$groupInput['ref']] = $this->createAnswer($groupInput, $uuid);
                }
            }

            if ($input['is_title']) {
                $titles[] = $answers[$input['ref']]['answer'];
            }
        }

        $title = implode(' ', $titles);
        $title = $title === '' ? $uuid : $title;

        return [
            'data' => [
                'type' => 'entry',
                'id' => $uuid,
                'attributes' => [
                    'form' => [
                        'ref' => $childFormRef,
                        'type' => 'hierarchy'
                    ]
                ],
                'relationships' => [
                    'parent' => [
                        'data' => [
                            'parent_form_ref' => $parentFormRef,
                            'parent_entry_uuid' => $parentEntryUuid
                        ]
                    ],
                    'branch' => [
                    ]
                ],
                'entry' => [
                    'entry_uuid' => $uuid,
                    'created_at' => Carbon::now()->format(config('epicollect.mappings.carbon_formats.ISO')),
                    'device_id' => '',
                    'platform' => 'WEB',
                    'title' => $title,
                    'answers' => $answers,
                    'project_version' => Project::version($projectSlug)
                ]
            ]
        ];
    }

    public function createBranchEntryPayload($formRef, $branchInputs, $ownerEntryUuid, $ownerInputRef): array
    {
        $projectSlug = array_get($this->projectDefinition, 'data.project.slug');
        $uuid = Uuid::uuid4()->toString();
        $titles = [];
        $answers = [];
        foreach ($branchInputs as $input) {

            $answers[$input['ref']] = $this->createAnswer($input, $uuid);
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                //add answers for group inputs
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    $answers[$groupInput['ref']] = $this->createAnswer($groupInput, $uuid);
                }
            }

            if ($input['is_title']) {
                $titles[] = $answers[$input['ref']]['answer'];
            }
        }

        $title = implode(' ', $titles);
        $title = $title === '' ? $uuid : $title;

        return [
            'data' => [
                'type' => 'branch_entry',
                'id' => $uuid,
                'attributes' => [
                    'form' => [
                        'ref' => $formRef,
                        'type' => 'hierarchy'
                    ]
                ],
                'relationships' => [
                    'parent' => [
                    ],
                    'branch' => [
                        'data' => [
                            'owner_entry_uuid' => $ownerEntryUuid,
                            'owner_input_ref' => $ownerInputRef
                        ]
                    ]
                ],
                'branch_entry' => [
                    'entry_uuid' => $uuid,
                    'created_at' => Carbon::now()->format(config('epicollect.mappings.carbon_formats.ISO')),
                    'device_id' => '',
                    'platform' => 'WEB',
                    'title' => $title,
                    'answers' => $answers,
                    'project_version' => Project::version($projectSlug)
                ]
            ]
        ];
    }

    public function createBranchEntryRow($user, $project, $role, $projectDefinition, $branchEntryPayload): array
    {
        $ownerInputRef = $branchEntryPayload['data']['relationships']['branch']['data']['owner_input_ref'];
        $entryStructure = new EntryStructureDTO();
        $requestedProject = new ProjectDTO(
            new ProjectDefinitionDTO(),
            new ProjectExtraDTO(),
            new ProjectMappingDTO(),
            new ProjectStatsDTO(),
            new ProjectMappingService()
        );
        $requestedProjectRole = new ProjectRoleDTO();
        $requestedProject->initAllDTOs(Project::findBySlug($project->slug));
        $requestedProjectRole->setRole($user, $project->id, $role);

        /* 4 - BUILD ENTRY STRUCTURE */
        $entryStructure->init(
            $branchEntryPayload['data'],
            $project->id,
            $requestedProjectRole
        );

        //get branch inputs
        $formRef = $branchEntryPayload['data']['attributes']['form']['ref'];
        $inputs = $requestedProject->getProjectDefinition()->getInputsByFormRef($formRef);

        $branchInputs = [];
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch') && $input['ref'] === $ownerInputRef) {
                $branchInputs = $input['branch'];
            }
        }


        $skippedInputsRefs = [];
        $this->multipleChoiceInputRefs = [];
        foreach ($branchInputs as $branchInput) {
            //add the valid answers to entryStructure (we assume they are valid)
            //imp: skip group and readme
            $inputsWithoutAnswer = array_keys(config('epicollect.strings.inputs_without_answers'));
            if (!in_array($branchInput['type'], $inputsWithoutAnswer)) {
                $answer = $branchEntryPayload['data']['branch_entry']['answers'][$branchInput['ref']];
                $entryStructure->addAnswerToEntry($branchInput, $answer);

                //need to add the possible answer, so they later added to the geojson object
                $this->addPossibleAnswers($entryStructure, $branchInput, $answer);

                //if location, add the geojson
                $this->addGeoJsonIfNeeded($entryStructure, $branchInput, $answer);

            }
            //if group, add answers for all the group inputs but skip the group owner
            if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {
                $skippedInputsRefs[] = $branchInput['ref'];
                //skip readme only (we cannot have nested groups)
                $groupInputs = $branchInput['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.readme')) {
                        $skippedInputsRefs[] = $groupInput['ref'];
                    } else {
                        $answer = $branchEntryPayload['data']['branch_entry']['answers'][$groupInput['ref']];
                        //add the valid answers to entryStructure (we assume they are valid)
                        $entryStructure->addAnswerToEntry($groupInput, $answer);
                        //need to add the possible answer, so they later added to the geojson object
                        $this->addPossibleAnswers($entryStructure, $groupInput, $answer);
                        //if location, add the geojson
                        $this->addGeoJsonIfNeeded($entryStructure, $groupInput, $answer);
                        if ($groupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                            $entryStructure->addGeoJsonObject($groupInput, $answer['answer']);
                            $entryStructure->addPossibleAnswersToGeoJson();
                        }
                    }
                }
            }

            if ($branchInput['type'] === config('epicollect.strings.inputs_type.readme')) {
                $skippedInputsRefs[] = $branchInput['ref'];
            }
        }

        $branchOwnerUuid = $branchEntryPayload['data']['relationships']['branch']['data']['owner_entry_uuid'];
        $owner = Entry::where('uuid', $branchOwnerUuid)->first();

        $entryStructure->setOwnerEntryID($owner->id);


        $createEntryService = new CreateEntryService();
        // If we received no errors, continue to insert answers and entry
        $createEntryService->create(
            $requestedProject,
            $entryStructure);

        return [
            'projectDefinition' => $projectDefinition,
            'project' => $project,
            'entryStructure' => $entryStructure,
            'skippedInputRefs' => $skippedInputsRefs,
            'multipleChoiceInputRefs' => $this->multipleChoiceInputRefs
        ];
    }


    private function generateMediaFilename($uuid, $type): string
    {
        $ext = '.';
        switch ($type) {
            case config('epicollect.strings.inputs_type.photo'):
                $ext .= config('epicollect.strings.media_file_extension.jpg');
                break;
            case config('epicollect.strings.inputs_type.audio'):
                // Android is okay with .mp4
                $ext .= config('epicollect.strings.media_file_extension.mp4');
                break;
            case config('epicollect.strings.inputs_type.video'):
                $ext .= config('epicollect.strings.media_file_extension.mp4');
                break;
        }
        return $uuid . '_' . Carbon::now()->timestamp . $ext;
    }

    private function generateStringFromRegex($regex): string
    {
        $randomString = $this->faker->regexify($regex);
        //filter out not accepted values for validation
        return str_replace(['<', '>', '='], '', $randomString);
    }

    private function addPossibleAnswers($entryStructure, $input, $answer)
    {
        //need to add the possible answer, so they later added to the geojson object
        if (in_array($input['type'], $this->multipleChoiceQuestionTypes)) {
            $this->multipleChoiceInputRefs[] = $input['ref'];
            //answer_ref comes as a string for radio and dropdown
            if (in_array($input['type'], ['radio', 'dropdown'])) {
                $entryStructure->addPossibleAnswer($answer['answer']);
            } else {
                //array for the other multiple choice type
                foreach ($answer['answer'] as $answerRef) {
                    $entryStructure->addPossibleAnswer($answerRef);
                }
            }
        }
    }

    private function addGeoJsonIfNeeded($entryStructure, $input, $answer)
    {
        if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
            $entryStructure->addGeoJsonObject($input, $answer['answer']);
            $entryStructure->addPossibleAnswersToGeoJson();
        }
    }
}