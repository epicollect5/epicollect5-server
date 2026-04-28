<?php

namespace ec5\Http\Validation\Schemas;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates a project definition JSON payload against the
 * Epicollect5 project export JSON Schema (Draft 2020-12).
 *
 * The schema is loaded once at construction time.
 * The schema $id is rewritten dynamically using APP_URL so the
 * version reference in responses reflects the actual server.
 *
 * Schema location: public/schemas/project.schema.json
 */
class ProjectSchemaValidator
{
    private Validator $validator;
    private object $schema;
    private array $errors = [];

    public function __construct()
    {
        $this->validator = new Validator();

        $schemaPath = public_path('schemas/project.schema.json');
        $schemaJson = file_get_contents($schemaPath);
        $this->schema = json_decode($schemaJson);

        // Rewrite $id to reflect the actual server (APP_URL) rather than
        // the hardcoded epicollect.net placeholder in the schema file.
        // e.g. https://my-server.com/schemas/project-export/v1.9.7
        if (isset($this->schema->{'$id'})) {
            $version = basename($this->schema->{'$id'}); // e.g. v1.9.7
            $this->schema->{'$id'} = rtrim(config('app.url'), '/') . '/schemas/project-export/' . $version;
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

        // maxErrors(0) tells opis to collect ALL errors instead of stopping at the first.
        // Without this, validation short-circuits and only the first violation is reported.
        $result = $this->validator->validate($payload, $this->schema, null, ['maxErrors' => 0]);

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
