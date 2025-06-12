<?php

namespace ec5\Http\Validation\Project\Mapping;

use ec5\DTO\ProjectMappingDTO;
use ec5\Http\Validation\ValidationBase;

class RuleMappingUpdate extends ValidationBase
{
    protected array $rules = [
        'action' => 'required|string|in:make-default,rename,update',
        'map_index' => 'required|integer',
        'is_default' => 'required_if:action,set_default|boolean',
        'name' => 'required_if:action,rename|string|min:3|max:10|regex:/^[A-Za-z0-9 \-\_]+$/',
        'mapping' => 'required_if:action,update|array'
    ];

    /**
     * @param ProjectMappingDTO $projectMapping
     * @param $newMapDetails
     */
    public function additionalChecks(ProjectMappingDTO $projectMapping, $newMapDetails): void
    {
        // Cant rename or update the default mapping (map_index 0)
        if (($this->data['action'] === 'rename' || $this->data['action'] === 'update') && $newMapDetails['map_index'] == 0) {
            $this->addAdditionalError('mapping', 'ec5_91');
            return;
        }

        // Check the map_index exists
        if (isset($newMapDetails['map_index'])) {
            if (!in_array($newMapDetails['map_index'], array_keys($projectMapping->getData()))) {
                $this->addAdditionalError('mapping', 'ec5_230');
                return;
            }
        }

        // Check not renaming to an existing name
        if (isset($newMapDetails['name'])) {
            // Check this map name is unique
            foreach ($projectMapping->getData() as $mapping) {
                if ($mapping['name'] == $newMapDetails['name']) {
                    $this->addAdditionalError('mapping', 'ec5_228');
                    return;
                }
            }
        }
    }
}
