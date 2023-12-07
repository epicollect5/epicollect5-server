<?php

namespace Tests\Generators;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class ProjectDefinitionGenerator
{
    private static function generateDescription(): string
    {
        $faker = Faker::create();
        return Str::limit($faker->paragraph(4), 1000); // Generate a sentence
    }

    private static function generateSmallDescription(): string
    {
        $faker = Faker::create();
        return Str::limit($faker->sentence(3), 100); // Generate a sentence
    }

    private static function generateQuestion(): string
    {
        $faker = Faker::create();
        $sentence = $faker->sentence($nbWords = 6); // Generate a sentence
        return rtrim($sentence, '.') . '?'; // Turn the sentence into a question
    }

    private static function generateProjectName(): string
    {
        $faker = Faker::create();
        return 'EC5 ' . $faker->regexify('[A-Za-z0-9]{10}'); // Change the regex pattern as needed
    }

    private static function generatePossibleAnswerValue(): string
    {
        $faker = Faker::create();
        $sentence = $faker->sentence($nbWords = 1); // Generate a sentence
        return rtrim($sentence, '.'); // Turn the sentence into a question
    }

    private static function getRandomSimpleInputType()
    {
        $types = config('epicollect.strings.inputs_type');
        $keysToRemove = [
            'integer',
            'decimal',
            'date',
            'time',
            'branch',
            'group',
            'searchsingle',
            'searchmultiple',
            'radio',
            'dropdown',
            'checkbox'
        ];

        foreach ($keysToRemove as $key) {
            unset($types[$key]);
        }

        $types = array_keys($types);
        $randomInputIndex = array_rand($types);
        return $types[$randomInputIndex];
    }

    private static function getRandomMultipleChoiceInputType(): string
    {
        return ['radio', 'dropdown', 'checkbox'][array_rand(['radio', 'dropdown', 'checkbox'])];
    }

    private static function getRandomMediaInputType(): string
    {
        return ['photo', 'audio', 'video'][array_rand(['photo', 'audio', 'video'])];
    }

    /**
     * Generate a sample project array for testing.
     */
    public static function createProject($howManyForms = 1, $withTitles = false): array
    {
        $projectRef = Generators::projectRef();
        $projectName = self::generateProjectName();
        $forms = [];

        for ($formIndex = 0; $formIndex < $howManyForms; $formIndex++) {
            $titlesLeft = $withTitles ? config('epicollect.limits.titlesMaxCount') : 0;
            $formRef = Generators::formRef($projectRef);
            //per each form, let's add some random inputs
            $inputs = [];
            $n = rand(1, 5);
            for ($inputIndex = 0; $inputIndex < $n; $inputIndex++) {
                $inputs[] = ProjectDefinitionGenerator::createSimpleInput($formRef);
                //set input as title (if possible)
                if ($titlesLeft > 0) {
                    $inputs[$inputIndex]['is_title'] = true;
                    $titlesLeft--;
                }

                $inputs[] = ProjectDefinitionGenerator::createIntegerInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createDecimalInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createTimeInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createDateInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef);
                //make sure there is at least 1 location input
                $inputs[] = ProjectDefinitionGenerator::createLocationInput($formRef);
                //add some media inputs
                $inputs[] = ProjectDefinitionGenerator::createMediaInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createMediaInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createMediaInput($formRef);
                //add one group
                $inputs[] = ProjectDefinitionGenerator::createGroup($formRef);
                //add one branch
                $inputs[] = ProjectDefinitionGenerator::createBranch($formRef);
            }

            //add 1 search input (limit is 5 across one project)
            $inputs[] = ProjectDefinitionGenerator::createSearchInput($formRef);

            $formName = 'Form ' . self::convertToWord($formIndex + 1);
            $forms[] = [
                "ref" => $formRef,
                "name" => $formName,
                "slug" => Str::slug($formName),
                "type" => "hierarchy",
                "inputs" => $inputs
            ];
        }

        return [
            "data" => [
                "id" => $projectRef,
                "type" => "project",
                "project" => [
                    "ref" => $projectRef,
                    "name" => $projectName,
                    "slug" => Str::slug($projectName),
                    "forms" => $forms,
                    "access" => "public",
                    "status" => "active",
                    "category" => "general",
                    "homepage" => "",
                    "logo_url" => "",
                    "created_at" => Carbon::now()->format('Y-m-d H:i:s'),
                    "visibility" => "hidden",
                    "description" => self::generateDescription(),
                    "entries_limits" => [
                    ],
                    "can_bulk_upload" => "nobody",
                    "small_description" => self::generateSmallDescription()
                ]
            ]
        ];
    }

    public static function createBranch($formRef): array
    {
        $inputRef = Generators::inputRef($formRef);

        $n = rand(1, 2);
        $branchInputs = [];

        for ($i = 0; $i < $n; $i++) {
            $branchInputs[] = ProjectDefinitionGenerator::createSimpleInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createTimeInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createDateInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createLocationInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createMediaInput($inputRef);
        }

        return [
            "max" => null,
            "min" => null,
            "ref" => $inputRef,
            "type" => "branch",
            "group" => [],
            "jumps" => [
            ],
            "regex" => null,
            "branch" => $branchInputs,
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => "Branch - " . self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => null,
            "possible_answers" => [],
            "set_to_current_datetime" => false
        ];
    }

    public static function createGroup($formRef): array
    {
        $inputRef = Generators::inputRef($formRef);
        $n = rand(1, 2);
        $groupInputs = [];

        for ($i = 0; $i < $n; $i++) {
            $groupInputs[] = ProjectDefinitionGenerator::createSimpleInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createTimeInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createDateInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createLocationInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createMediaInput($inputRef);
        }

        return [
            "max" => null,
            "min" => null,
            "ref" => $inputRef,
            "type" => "group",
            "branch" => [],
            "jumps" => [],
            "regex" => null,
            "group" => $groupInputs,
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => "Group - " . self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => null,
            "possible_answers" => [],
            "set_to_current_datetime" => false
        ];
    }

    public static function createSimpleInput($formRef): array
    {
        $type = self::getRandomSimpleInputType();
        $regex = null;

        //on text and textarea, add a regex
        if (in_array($type, ['text', 'textarea'])) {
            $regex = self::getRandomRegex();
        }

        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => self::getRandomSimpleInputType(),
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => $regex,
            "branch" => [],
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => null,
            "possible_answers" => [],
            "set_to_current_datetime" => false
        ];
    }

    public static function createIntegerInput($formRef): array
    {
        $minmax = self::generateRandomMinMaxInteger();

        return [
            "max" => $minmax['max'],
            "min" => $minmax['min'],
            "ref" => Generators::inputRef($formRef),
            "type" => 'integer',
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => null,
            "branch" => [],
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => null,
            "possible_answers" => [],
            "set_to_current_datetime" => false
        ];
    }

    public static function createDecimalInput($formRef): array
    {
        $minmax = self::generateRandomMinMaxDecimal();

        return [
            "max" => $minmax['max'],
            "min" => $minmax['min'],
            "ref" => Generators::inputRef($formRef),
            "type" => 'decimal',
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => null,
            "branch" => [],
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => null,
            "possible_answers" => [],
            "set_to_current_datetime" => false
        ];
    }

    public static function createDateInput($formRef): array
    {
        $formats = array_keys(config('epicollect.strings.date_formats'));
        $datetimeFormat = array_rand(array_flip($formats));
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => 'date',
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => null,
            "branch" => [],
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => $datetimeFormat,
            "possible_answers" => [],
            "set_to_current_datetime" => false
        ];
    }

    public static function createLocationInput($formRef): array
    {
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => 'location',
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => null,
            "branch" => [],
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => null,
            "possible_answers" => [],
            "set_to_current_datetime" => false
        ];
    }

    public static function createTimeInput($formRef): array
    {
        $formats = array_keys(config('epicollect.strings.time_formats'));
        $datetimeFormat = array_rand(array_flip($formats));
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => 'time',
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => null,
            "branch" => [],
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => $datetimeFormat,
            "possible_answers" => [],
            "set_to_current_datetime" => false
        ];
    }

    public static function createMultipleChoiceInput($formRef): array
    {
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => self::getRandomMultipleChoiceInputType(),
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => null,
            "branch" => [],
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => null,
            "possible_answers" => self::createPossibleAnswers(rand(1, 50)),
            "set_to_current_datetime" => false
        ];
    }

    public static function createMediaInput($formRef): array
    {
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => self::getRandomMediaInputType(),
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => null,
            "branch" => [],
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => self::generateQuestion(),
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => null,
            "possible_answers" => self::createPossibleAnswers(rand(1, 50)),
            "set_to_current_datetime" => false
        ];
    }

    public static function createSearchInput($formRef): array
    {
        $types = ['searchsingle', 'searchmultiple'];
        $randomIndex = array_rand($types);
        $randomType = $types[$randomIndex];

        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => $randomType,
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => null,
            "branch" => [],
            "verify" => false,
            "default" => "",
            "is_title" => false,
            "question" => "Name",
            "uniqueness" => "none",
            "is_required" => false,
            "datetime_format" => null,
            "possible_answers" => self::createPossibleAnswers(rand(1, 50)),
            "set_to_current_datetime" => false
        ];
    }

    private static function createPossibleAnswers($count): array
    {
        $possibleAnswers = [];
        for ($i = 0; $i < $count; $i++) {
            $possibleAnswers[] = [
                'answer_ref' => uniqid(),
                'answer' => self::generatePossibleAnswerValue()
            ];
        }
        return $possibleAnswers;
    }

    private static function convertToWord($number): ?string
    {
        $words = [
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten'
        ];

        return $words[$number] ?? null;
    }


    private static function generateRandomMinMaxInteger(): array
    {
        $min = rand(PHP_INT_MIN, PHP_INT_MAX);
        $max = rand($min, PHP_INT_MAX);

        if ($min > $max) {
            $temp = $max;
            $max = $min;
            $min = $temp;
        }

        return ['min' => $min, 'max' => $max];
    }

    private static function generateRandomMinMaxDecimal(): array
    {
        $min = rand(PHP_INT_MIN, PHP_INT_MAX - 1) / mt_getrandmax();
        $max = rand(PHP_INT_MIN, PHP_INT_MAX - 1) / mt_getrandmax();

        if ($min > $max) {
            $temp = $max;
            $max = $min;
            $min = $temp;
        }

        return ['min' => $min, 'max' => $max];
    }

    private static function getRandomRegex(): string
    {
        $regexes = ['^[A-Za-z]{1,20}$', '^[0-9]+$', '^[a-zA-Z0-9,]{1,10}$'];
        $randomIndex = array_rand($regexes);
        return $regexes[$randomIndex];
    }
}