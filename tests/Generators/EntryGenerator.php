<?php

namespace Tests\Generators;

use Carbon\Carbon;
use ec5\Models\Eloquent\Project;
use Faker\Factory as Faker;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class EntryGenerator
{
    const SYMBOLS = ['!', '@', '€', '£', '#', '$', '%', '^', '&', '*', '"', '\'', '\\', '{', ',', '.', '?', '|', '<', '>', '~', '`'];
    const LOCALES = ['it_IT', 'en_US', 'de_DE', 'es_ES', 'en_GB', 'fr_FR'];

    private $faker;
    private $randomLocale;
    private $projectDefinition;

    public function __construct(array $projectDefinition)
    {
        // Pick a random locale from the array
        $this->randomLocale = self::LOCALES[array_rand(self::LOCALES)];
        // Set Faker to use the random locale
        $this->faker = Faker::create($this->randomLocale);
        $this->projectDefinition = $projectDefinition;
    }

    private function createAnswer($input, $uuid): array
    {
        $answer = '';

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
                $formatDate = config('epicollect.strings.carbon_formats.fake_date');
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
                $formatTime = config('epicollect.strings.carbon_formats.fake_time');
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

    public function createParentEntry($formRef): array
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
                    'created_at' => Carbon::now()->format(config('epicollect.strings.carbon_formats.ISO')),
                    'device_id' => '',
                    'platform' => 'WEB',
                    'title' => $title,
                    'answers' => $answers,
                    'project_version' => Project::version($projectSlug)
                ]
            ]
        ];
    }

    public function createChildEntry($childFormRef, $parentFormRef, $parentEntryUuid): array
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
                    'created_at' => Carbon::now()->format(config('epicollect.strings.carbon_formats.ISO')),
                    'device_id' => '',
                    'platform' => 'WEB',
                    'title' => $title,
                    'answers' => $answers,
                    'project_version' => Project::version($projectSlug)
                ]
            ]
        ];
    }

    public function createBranchEntry($formRef, $branchInputs, $ownerEntryUuid, $ownerInputRef): array
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
                    'created_at' => Carbon::now()->format(config('epicollect.strings.carbon_formats.ISO')),
                    'device_id' => '',
                    'platform' => 'WEB',
                    'title' => $title,
                    'answers' => $answers,
                    'project_version' => Project::version($projectSlug)
                ]
            ]
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
}