<?php

namespace ec5\Http\Validation\Project\Mapping;

use ec5\Http\Validation\ValidationBase;

class RuleMappingInput extends ValidationBase
{
    /**
     * @var array
     */
    protected array $rules = [
        'hide' => 'required|boolean',
        'group' => 'present|array',
        'branch' => 'present|array',
        'map_to' => 'required|string|max:20|regex:/^[A-Za-z0-9\_]+$/',
        'possible_answers' => 'present|array'
    ];

    /**
     * @var array
     */
    protected array $uniqueMappedNames = [];

    /**
     * @param $mapToName ]
     * @param $inputRef
     * @return bool
     */
    public function checkAdditional($mapToName, $inputRef): bool
    {
        // Check the map_to value is unique
        if (in_array($mapToName, $this->uniqueMappedNames)) {
            $this->addAdditionalError($inputRef, 'ec5_234');
            return false;
        }

        //check map_to value is not a reserved value
        $mapToReserved = array_keys(config('epicollect.strings.map_to_reserved'));
        if (in_array($mapToName, $mapToReserved)) {
            $this->addAdditionalError($inputRef, 'ec5_227');
            return false;
        }

        // Add this value to the unique list of mapTos
        $this->uniqueMappedNames[] = $mapToName;

        return true;
    }

    /**
     * Reset the unique mapped names list
     * We want to do this every time we come
     * across a new form mapping
     */
    public function resetUniqueMappedNames(): void
    {
        $this->uniqueMappedNames = [];
    }
}
