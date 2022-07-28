<?php

namespace ec5\Http\Validation\Entries\Archive;

use ec5\Http\Validation\ValidationBase;
use ec5\Models\Entries\EntryStructure;
use ec5\Models\Projects\Project;

class RuleArchive extends ValidationBase
{
    /**
     * @var array
     */
    protected $rules = [
        'type' => 'required|string|in:archive',
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

        'archive' => 'required',
        'archive.entry_uuid' => 'required|string|min:36|max:36|same:id',
    ];

    /**
     * @param Project $project
     * @param EntryStructure $entryStructure
     */
    public function additionalChecks(Project $project, EntryStructure $entryStructure)
    {

        $projectExtra = $project->getProjectExtra();

        // Check if form ref exists
        if (count($projectExtra->getFormDetails($entryStructure->getFormRef())) == 0) {
            $this->addAdditionalError($entryStructure->getFormRef(), 'ec5_15');
            return;
        }

        // Check if branch exists (if supplied)
        if (!empty($entryStructure->getOwnerInputRef()) && !$projectExtra->branchExists($entryStructure->getFormRef(), $entryStructure->getOwnerInputRef())) {
            $this->addAdditionalError($entryStructure->getFormRef(), 'ec5_99');
            return;
        }
    }
}
