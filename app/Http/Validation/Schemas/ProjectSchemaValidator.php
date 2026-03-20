<?php

namespace ec5\Http\Validation\Schemas;

use Log;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Throwable;

/**
 * Validates a project definition JSON payload against the
 * Epicollect5 project export JSON Schema (Draft 2020-12).
 *
 * The schema is loaded once and cached in the singleton instance,
 * which is bound in AppServiceProvider.
 *
 * Schema location: public/schemas/ec5-project-schema.json
 */
class ProjectSchemaValidator
{
    private Validator $validator;
    private object $schema;
    private array $errors = [];

    public function __construct()
    {
        $this->validator = new Validator();

        // Load schema once at construction (singleton = loaded once per request lifecycle)
        try {
            $schemaPath = public_path('schemas/project.schema.json');
            $schemaJson = file_get_contents($schemaPath);
            $this->schema = json_decode($schemaJson);
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Validate the incoming request data against the JSON Schema.
     *
     * @param array $data  The raw request payload (request->all())
     * @return bool        True if valid, false if schema violations found
     */
    public function validate(array $data): bool
    {
        $this->errors = [];

        // opis/json-schema requires the data as a decoded object, not an array
        $payload = json_decode(json_encode($data));

        $result = $this->validator->validate($payload, $this->schema);

        if (!$result->isValid()) {
            $formatter = new ErrorFormatter();
            $formatted = $formatter->format($result->error(), true);

            // Flatten to a simple list of human-readable messages
            $this->errors = $this->flattenErrors($formatted);
        }

        return empty($this->errors);
    }

    /**
     * Returns true if schema validation produced errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Returns the raw list of schema violation strings.
     * Used by Response::apiSchemaError() macro to format the error response.
     *
     * Format: ['/data/project/category: The data should match one item from enum', ...]
     */
    public function violations(): array
    {
        return $this->errors;
    }

    /**
     * Returns the $id of the schema used for validation.
     * Used to include the schema reference in successful responses.
     */
    public function schemaId(): string
    {
        return $this->schema->{'$id'} ?? 'unknown';
    }

    /**
     * Recursively flatten the opis error tree into a simple string array.
     */
    private function flattenErrors(array $errors, string $prefix = ''): array
    {
        $flat = [];
        foreach ($errors as $path => $messages) {
            $fullPath = $prefix ? "$prefix.$path" : $path;
            if (is_array($messages)) {
                foreach ($messages as $message) {
                    if (is_array($message)) {
                        $flat = array_merge($flat, $this->flattenErrors($message, $fullPath));
                    } else {
                        $flat[] = "$fullPath: $message";
                    }
                }
            } else {
                $flat[] = "$fullPath: $messages";
            }
        }
        return $flat;
    }
}
