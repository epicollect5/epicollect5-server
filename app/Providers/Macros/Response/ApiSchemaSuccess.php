<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Macro: Response::apiSchemaSuccess($projectRef, $name, $schemaId)
 *
 * Used exclusively for successful JSON Schema validation responses.
 * Formats the response as:
 * {
 *   "data": {
 *     "type":         "project-json-validation",
 *     "id":           "abc123...",
 *     "project": {
 *       "name":       "My Project",
 *       "slug":       "my-project"
 *     },
 *     "validation":   "passed",
 *     "schema":       "https://epicollect.net/schemas/project-export/v1.4.0",
 *     "validated_at": "2026-03-19T10:00:00+00:00"
 *   }
 * }
 *
 * Usage:
 *   return Response::apiSchemaSuccess($newProjectRef, $payload['name'], $projectSchemaValidator->schemaId());
 */
class ApiSchemaSuccess extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro('apiSchemaSuccess', function (
            string $projectRef,
            string $name,
            string $schemaId
        ) {
            return new JsonResponse(
                [
                    'data' => [
                        'type'         => 'project-json-validation',
                        'id'           => $projectRef,
                        'project'      => [
                            'name' => $name,
                            'slug' => Str::slug($name, '-'),
                        ],
                        'validation'   => 'passed',
                        'schema'       => $schemaId,
                        'validated_at' => now()->toIso8601String(),
                    ]
                ],
                200,
                ['Content-Type' => 'application/vnd.api+json; charset=utf-8'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        });
    }
}
