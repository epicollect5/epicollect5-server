<?php

namespace ec5\Http\Validation\Project\Mapping;

use ec5\DTO\ProjectDTO;

class RuleImportProjectMapping
{
    protected RuleMappingCreate $ruleMappingCreate;
    protected RuleMappingStructure $ruleMappingStructure;
    protected array $errors = [];

    public function __construct(
        RuleMappingCreate $ruleMappingCreate,
        RuleMappingStructure $ruleMappingStructure
    ) {
        $this->ruleMappingCreate = $ruleMappingCreate;
        $this->ruleMappingStructure = $ruleMappingStructure;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function validate(ProjectDTO $project, mixed $projectMappings): bool
    {
        $this->errors = [];

        if ($projectMappings === null) {
            return true;
        }

        if (!is_array($projectMappings)) {
            $this->errors['project_mapping'] = ['ec5_29'];
            return false;
        }

        if (count($projectMappings) === 0) {
            return true;
        }

        $mapping = $this->getImportedMapping($projectMappings);

        if (!is_array($mapping)) {
            $this->errors['project_mapping'] = ['ec5_29'];
            return false;
        }

        $payload = [
            'name' => $mapping['name'] ?? null,
            'is_default' => true
        ];

        if (($payload['name'] ?? '') !== config('epicollect.mappings.default_mapping_name')) {
            $this->ruleMappingCreate->resetErrors();
            $this->ruleMappingCreate->validate($payload);
            if ($this->ruleMappingCreate->hasErrors()) {
                $this->errors = $this->ruleMappingCreate->errors();
                return false;
            }

            $this->ruleMappingCreate->additionalChecks($project->getProjectMapping(), $payload);
            if ($this->ruleMappingCreate->hasErrors()) {
                $this->errors = $this->ruleMappingCreate->errors();
                return false;
            }
        }

        $this->ruleMappingStructure->resetErrors();
        $this->ruleMappingStructure->validate($mapping);
        if ($this->ruleMappingStructure->hasErrors()) {
            $this->errors = $this->ruleMappingStructure->errors();
            return false;
        }

        $this->ruleMappingStructure->additionalChecks($project, $mapping);
        if ($this->ruleMappingStructure->hasErrors()) {
            $this->errors = $this->ruleMappingStructure->errors();
            return false;
        }

        return true;
    }

    private function getImportedMapping(array $projectMappings): mixed
    {
        foreach ($projectMappings as $mapping) {
            if (($mapping['is_default'] ?? false) === true) {
                return $mapping;
            }
        }

        return $projectMappings[0] ?? null;
    }
}
