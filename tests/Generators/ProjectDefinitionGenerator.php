<?php

namespace Tests\Generators;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use Illuminate\Support\Str;

class ProjectDefinitionGenerator
{
    /**
     * Generate a sample project array for testing.
     */
    public static function createProject($howManyForms = 1): array
    {
        $projectRef = Generators::projectRef();
        $forms = [];

        for ($i = 0; $i < $howManyForms; $i++) {
            $formRef = Generators::formRef($projectRef);
            $inputRef = Generators::inputRef($formRef);
            $forms[] = [
                "ref" => $formRef,
                "name" => "Form " . $i,
                "slug" => "form-" . $i,
                "type" => "hierarchy",
                "inputs" => [
                    [
                        "max" => null,
                        "min" => null,
                        "ref" => $inputRef,
                        "type" => "radio",
                        "group" => [
                        ],
                        "jumps" => [
                        ],
                        "regex" => null,
                        "branch" => [
                        ],
                        "verify" => false,
                        "default" => "",
                        "is_title" => false,
                        "question" => "Sex",
                        "uniqueness" => "none",
                        "is_required" => false,
                        "datetime_format" => null,
                        "possible_answers" => [
                            [
                                "answer" => "Male",
                                "answer_ref" => uniqid()
                            ],
                            [
                                "answer" => "Female",
                                "answer_ref" => uniqid()
                            ]
                        ],
                        "set_to_current_datetime" => false
                    ]
                ]
            ];
        }

        return [
            "data" => [
                "id" => $projectRef,
                "type" => "project",
                "project" => [
                    "ref" => $projectRef,
                    "name" => "Just a test project",
                    "slug" => "just-a-test-project",
                    "forms" => $forms,
                    "access" => "public",
                    "status" => "active",
                    "category" => "general",
                    "homepage" => "",
                    "logo_url" => "",
                    "created_at" => Carbon::now()->format('Y-m-d H:i:s'),
                    "visibility" => "hidden",
                    "description" => "",
                    "entries_limits" => [
                    ],
                    "can_bulk_upload" => "nobody",
                    "small_description" => "This a sample project definition just for unit tests"
                ]
            ]
        ];
    }

    /**
     * Generate a sample project array with a specific name.
     */
    public static function create(string $name): array
    {
        $project = self::createProject();
        $project['data']['project']['name'] = $name;
        $project['data']['project']['slug'] = Str::slug($name, '-');

        return $project;
    }

    // You can add more methods for generating various types of data as needed.
}