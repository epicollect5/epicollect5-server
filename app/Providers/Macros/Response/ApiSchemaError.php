<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

/**
 * Macro: Response::apiSchemaError($httpStatusCode, $schemaId, $violations)
 *
 * Used exclusively for JSON Schema validation failures.
 * Formats each schema violation as:
 * {
 *   "schema":   "https://epicollect.net/schemas/project-export/v1.1.0",
 *   "title":  "/data/project/category: The data should match one item from enum",
 *   "source": "schema"
 * }
 *
 * Usage:
 *   return Response::apiSchemaError('400', $projectSchemaValidator->schemaId(), $projectSchemaValidator->violations());
 */
class ApiSchemaError extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro('apiSchemaError', function (
            string $httpStatusCode,
            string $schemaId,
            array  $violations
        ) {
            $errors = array_map(function (string $message) use ($schemaId) {
                return [
                    'schema'   => $schemaId,
                    'title'  => $message,
                    'source' => 'schema-validator',
                ];
            }, $violations);

            return new JsonResponse(
                ['errors' => $errors],
                (int) $httpStatusCode,
                ['Content-Type' => 'application/vnd.api+json; charset=utf-8'],
                0
            );
        });
    }
}
