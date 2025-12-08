<?php

namespace ec5\Http\Validation\Entries\Delete;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;

class RuleDelete extends ValidationBase
{
    /**
     * @var array
     */
    protected array $rules = [
        'type' => 'required|string|in:delete',
        'id' => 'required|string|min:36|max:36',
        'attributes' => 'required|array',
        'attributes.form.ref' => 'required|min:46|max:46',
        'attributes.form.type' => 'required|in:hierarchy',
        'relationships' => 'required|array',
        'relationships.parent' => 'present|array',
        'relationships.parent.data' => 'array',
        'relationships.parent.data.parent_form_ref' => 'min:46|max:46',
        'relationships.parent.data.parent_entry_uuid' => 'min:36|max:36',
        'relationships.branch' => 'present|array',
        'relationships.branch.data' => 'array',
        'relationships.branch.data.owner_input_ref' => 'min:60|max:60',
        'relationships.branch.data.owner_entry_uuid' => 'min:36|max:36',
        'delete' => 'required',
        'delete.entry_uuid' => 'required|string|min:36|max:36|same:id',
    ];

    /**
     * Performs additional validation checks for deleting an entry based on project and entry structure data.
     *
     * Ensures the form reference exists in the project, verifies parent or owner UUIDs as required by the entry's context, and checks for the existence of a branch if applicable.
     *
     * @return bool True if all additional checks pass; false if any validation fails.
     */
    public function additionalChecks(ProjectDTO $project, EntryStructureDTO $entryStructure): bool
    {
        $projectExtra = $project->getProjectExtra();
        // Check if form ref exists
        if (count($projectExtra->getFormDetails($entryStructure->getFormRef())) == 0) {
            $this->addAdditionalError($entryStructure->getFormRef(), 'ec5_15');
            return false;
        }

        //if the current form ref is not the top parent, we must have a parent entry uuid
        $topParentFormRef = $project->getProjectDefinition()->getData()['project']['forms'][0]['ref'];
        if ($entryStructure->getFormRef() !== $topParentFormRef) {

            //if the entry is a branch, we don't need a parent uuid but an owner uuid
            $isBranch = !empty($entryStructure->getOwnerInputRef());
            if ($isBranch) {
                return true;
            }
            //if the entry is not a branch, we need a parent uuid
            if (empty($entryStructure->getParentUuid())) {
                $this->addAdditionalError($entryStructure->getFormRef(), 'ec5_359');
                return false;
            }
        }

        // Check if branch exists (if supplied)
        if (!empty($entryStructure->getOwnerInputRef()) && !$projectExtra->branchExists($entryStructure->getFormRef(), $entryStructure->getOwnerInputRef())) {
            $this->addAdditionalError($entryStructure->getFormRef(), 'ec5_99');
            return false;
        }

        return true;
    }
}
