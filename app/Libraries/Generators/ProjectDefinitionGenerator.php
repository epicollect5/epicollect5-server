<?php

namespace ec5\Libraries\Generators;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

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
        $sentence = $faker->sentence(6); //Generate a sentence
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
        $sentence = $faker->sentence(1); // Generate a sentence
        return rtrim($sentence, '.'); // Turn the sentence into a question
    }


    private static function getRandomSimpleInputType()
    {
        $faker = Faker::create();
        return $faker->randomElement(['text', 'textarea', 'phone']);
    }

    private static function getRandomMultipleChoiceInputType(): string
    {
        return ['radio', 'dropdown', 'checkbox'][array_rand(['radio', 'dropdown', 'checkbox'])];
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
            }
            $inputs[] = ProjectDefinitionGenerator::createTextInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createIntegerInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createDecimalInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createPhoneInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createTimeInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createDateInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createTextBoxInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createBarcodeInput($formRef);

            $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef, 'radio');
            $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef, 'dropdown');
            $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef, 'checkbox');

            $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($formRef);
            //make sure there is at least 1 location input
            $inputs[] = ProjectDefinitionGenerator::createLocationInput($formRef);
            //add some media inputs
            $inputs[] = ProjectDefinitionGenerator::createPhotoInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createAudioInput($formRef);
            $inputs[] = ProjectDefinitionGenerator::createVideoInput($formRef);
            //add two groups
            $inputs[] = ProjectDefinitionGenerator::createGroup($formRef);
            $inputs[] = ProjectDefinitionGenerator::createGroup($formRef);
            // add two branches
            $inputs[] = ProjectDefinitionGenerator::createBranch($formRef, $withTitles);
            $inputs[] = ProjectDefinitionGenerator::createBranch($formRef, $withTitles);

            // add 1 search input (limit is 5 across one project)
            $inputs[] = ProjectDefinitionGenerator::createSearchInput($formRef);
            if ($howManyForms === 1) {
                $inputs[] = ProjectDefinitionGenerator::createSearchSingleInput($formRef);
                $inputs[] = ProjectDefinitionGenerator::createSearchMultipleInput($formRef);
            }

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

    public static function createBranch($formRef, $withTitles = false): array
    {
        $inputRef = Generators::inputRef($formRef);
        $titleMaxCount = config('epicollect.limits.titlesMaxCount');

        $n = rand(1, 1);
        $branchInputs = [];

        $titlesLeft = $withTitles ? $titleMaxCount : 0;
        for ($branchInputIndex = 0; $branchInputIndex < $titleMaxCount; $branchInputIndex++) {
            $branchInputs[] = ProjectDefinitionGenerator::createSimpleInput($inputRef);
            //set branch input as title (if possible)
            if ($titlesLeft > 0) {
                $branchInputs[$branchInputIndex]['is_title'] = true;
                $titlesLeft--;
            }
        }

        for ($i = 0; $i < $n; $i++) {
            $branchInputs[] = ProjectDefinitionGenerator::createTextInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createIntegerInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createDecimalInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createPhoneInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createTextBoxInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createTimeInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createDateInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef, 'radio');
            $branchInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef, 'dropdown');
            $branchInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef, 'checkbox');
            $branchInputs[] = ProjectDefinitionGenerator::createLocationInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createMediaInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createPhotoInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createAudioInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createVideoInput($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createGroup($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createGroup($inputRef);
            $branchInputs[] = ProjectDefinitionGenerator::createBarcodeInput($inputRef);
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
        $n = rand(1, 1);
        $groupInputs = [];

        for ($i = 0; $i < $n; $i++) {
            $groupInputs[] = ProjectDefinitionGenerator::createTextInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createIntegerInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createDecimalInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createPhoneInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createSimpleInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createTimeInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createDateInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef, 'radio');
            $groupInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef, 'dropdown');
            $groupInputs[] = ProjectDefinitionGenerator::createMultipleChoiceInput($inputRef, 'checkbox');
            $groupInputs[] = ProjectDefinitionGenerator::createLocationInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createMediaInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createTextBoxInput($inputRef);
            $groupInputs[] = ProjectDefinitionGenerator::createBarcodeInput($inputRef);
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

    public static function createTextInput($formRef): array
    {
        $type = config('epicollect.strings.inputs_type.text');
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => $type,
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => '',
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

    public static function createTextBoxInput($formRef): array
    {
        $type = config('epicollect.strings.inputs_type.textarea');
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => $type,
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => '',
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

    public static function createBarcodeInput($formRef): array
    {
        $type = config('epicollect.strings.inputs_type.barcode');
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => $type,
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => '',
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


    public static function createPhoneInput($formRef): array
    {
        $type = config('epicollect.strings.inputs_type.phone');
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => $type,
            "group" => [
            ],
            "jumps" => [
            ],
            "regex" => '',
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
        $faker = Faker::create();
        $formats = array_keys(config('epicollect.strings.date_formats'));
        $datetimeFormat = $faker->randomElement($formats);
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

    public static function createMultipleChoiceInput($formRef, $type = null): array
    {
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => $type ?? self::getRandomMultipleChoiceInputType(),
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

    public static function createPhotoInput($formRef): array
    {
        return self::createMediaInput($formRef, 'photo');
    }

    public static function createAudioInput($formRef): array
    {
        return self::createMediaInput($formRef, 'audio');
    }

    public static function createVideoInput($formRef): array
    {
        return self::createMediaInput($formRef, 'video');
    }

    public static function createMediaInput($formRef, $type = 'photo'): array
    {
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => $type,
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


    public static function createSearchMultipleInput($formRef): array
    {
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => 'searchmultiple',
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

    public static function createSearchSingleInput($formRef): array
    {
        return [
            "max" => null,
            "min" => null,
            "ref" => Generators::inputRef($formRef),
            "type" => 'searchsingle',
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

    //use strings to avoid rounding issues when doing JSON <> string conversions
    private static function generateRandomMinMaxDecimal(): array
    {
        $min = rand(PHP_INT_MIN, PHP_INT_MAX - 1) / mt_getrandmax();
        $max = rand(PHP_INT_MIN, PHP_INT_MAX - 1) / mt_getrandmax();

        if ($min > $max) {
            $temp = $max;
            $max = $min;
            $min = $temp;
        }

        return ['min' => (string)$min, 'max' => (string)$max];
    }

    private static function getRandomRegex(): string
    {
        $regexes = ['^[A-Za-z]{1,20}$', '^[0-9]+$', '^[a-zA-Z0-9,]{1,10}$'];
        $randomIndex = array_rand($regexes);
        return $regexes[$randomIndex];
    }
}
