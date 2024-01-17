<?php

namespace ec5\Http\Validation\Project\Mapping;

use ec5\Http\Validation\ValidationBase;
use ec5\Models\Projects\ProjectMapping;

class RuleMappingDelete extends ValidationBase
{

    protected $rules = [
        'map_index' => 'required|integer|not_in:0',
    ];

    /**
     * RuleMappingDelete constructor.
     */
    public function __construct()
    {

        $this->messages = array_merge($this->messages, [
            'not_in' => 'ec5_238'
        ]);

    }

    /**
     * @param ProjectMapping $projectMapping
     * @param $newMapDetails
     */
    public function additionalChecks(ProjectMapping $projectMapping, $newMapDetails)
    {
        // Check the map_index exists
        if (isset($newMapDetails['map_index'])) {
            if (!in_array($newMapDetails['map_index'], array_keys($projectMapping->getData()))) {
                $this->addAdditionalError('mapping', 'ec5_230');
            }
        }
    }
}
