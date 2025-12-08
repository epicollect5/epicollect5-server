<?php

namespace ec5\DTO;

use ec5\Libraries\Utilities\Arrays;

/*
|--------------------------------------------------------------------------
| Project Definition Model
|--------------------------------------------------------------------------
| A DTO for the JSON Project Definition
|
*/

class ProjectDefinitionDTO extends ProjectDTOBase
{
    public function create(array $data): void
    {
        // Retrieve project definition template
        $projectDefinitionStructure = [
            'id' => '{{project_ref}}',
            'type' => 'project',
            'project' => [
                'ref' => '{{project_ref}}',
                'name' => '',
                'slug' => '',
                'forms' => [
                    [
                        'ref' => '{{form_ref}}',
                        'name' => '',
                        'slug' => '',
                        'type' => 'hierarchy',
                        'inputs' => []
                    ]
                ],
                'access' => '',
                'status' => '',
                'logo_url' => '',
                'visibility' => '',
                'small_description' => '',
                'description' => '',
                'category' => 'general',
                'entries_limits' => []
            ]
        ];
        // Replace key values from $data into the $projectStructure
        $this->data = Arrays::merge($projectDefinitionStructure, $data);
    }

    public function updateProjectDetails(array $data): void
    {
        foreach ($data as $key => $value) {
            if (isset($this->data['project'][$key])) {
                $this->data['project'][$key] = $value;
            }
        }
    }

    public function getFirstFormRef(): string
    {
        return $this->data['project']['forms'][0]['ref'];
    }

    public function getParentFormRef($formRef)
    {
        $forms = $this->data['project']['forms'];
        foreach ($forms as $formIndex => $form) {
            if ($form['ref'] === $formRef) {
                // If the form is the first one, it has no parent
                if ($formIndex === 0) {
                    return null;
                }
                // Otherwise, return the ref of the previous form as the parent
                return $forms[$formIndex - 1]['ref'];
            }
        }
        return null;
    }

    public function getInputsByFormRef($formRef)
    {
        $inputs = [];
        foreach ($this->data['project']['forms'] as $form) {
            if ($form['ref'] === $formRef) {
                $inputs = $form['inputs'];
                break;
            }
        }
        return $inputs;
    }

    public function getEntriesLimit($ref): ?int
    {
        return $this->data['project']['entries_limits'][$ref] ?? null;
    }

    public function setEntriesLimit($ref, $limitTo): void
    {
        $this->data['project']['entries_limits'][$ref] = $limitTo;
    }

    public function clearEntriesLimits(): void
    {
        $this->data['project']['entries_limits'] = [];
    }

    public function addEntriesLimits($entriesLimits): void
    {
        $this->data['project']['entries_limits'] = $entriesLimits;
    }
}
