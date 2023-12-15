<?php

namespace ec5\Http\Validation\Project\Mapping;

use ec5\Http\Validation\ValidationBase;
use Config;
use ec5\Models\Projects\ProjectMapping;

class RuleMappingCreate extends ValidationBase
{

    protected $rules = [
        'name' => 'required|min:3|max:20|regex:/^[A-Za-z0-9 \-\_]+$/',
        //'is_default' => 'required|boolean'
    ];

    /**
     * @param ProjectMapping $projectMapping
     * @param $newMapDetails
     */
    public function additionalChecks(ProjectMapping $projectMapping, $newMapDetails)
    {

        // Check we haven't reached the allowed number of maps
        if ($projectMapping->getMapCount() == config('epicollect.limits.project_mappings.allowed_maps')) {
            $this->addAdditionalError('mapping', 'ec5_229');
            return;
        }

        // Check this map name is unique
        foreach ($projectMapping->getData() as $mappingIndex => $mapping) {
            if ($mapping['name'] == $newMapDetails['name']) {
                $this->addAdditionalError('mapping', 'ec5_228');
                return;
            }
        }

    }
}
