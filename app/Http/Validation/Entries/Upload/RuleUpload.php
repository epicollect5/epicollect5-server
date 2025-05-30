<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;
use ec5\Libraries\Utilities\Arrays;
use ec5\Libraries\Utilities\Strings;

class RuleUpload extends ValidationBase
{
    protected array $rules = [
        'type' => 'required|in:entry,branch_entry,file_entry',
        'entry' => 'required_if:type,entry',
        'branch_entry' => 'required_if:type,branch_entry',
        'file_entry' => 'required_if:type,file_entry',
        'id' => 'required|min:36|max:36',

        // Entry
        'entry.entry_uuid' => 'required_if:type,entry|same:id|min:36|max:36',
        'entry.created_at' => 'required_if:type,entry|date',
        'entry.platform' => '',
        'entry.device_id' => '',
        'entry.project_version' => 'required_if:type,entry|date',

        // Branch Entry
        'branch_entry.entry_uuid' => 'required_if:type,branch_entry|same:id|min:36|max:36',
        'branch_entry.created_at' => 'required_if:type,branch_entry|date',
        'branch_entry.platform' => '',
        'branch_entry.device_id' => '',
        'branch_entry.project_version' => 'required_if:type,branch_entry|date',

        // File Entry
        'file_entry.entry_uuid' => 'required_if:type,file_entry|same:id|min:36|max:36',
        'file_entry.name' => 'required_if:type,file_entry|string',
        'file_entry.type' => '',
        'file_entry.input_ref' => 'required_if:type,file_entry',
        'file_entry.project_version' => 'required_if:type,file_entry|date',

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
        'relationships.branch.data.owner_entry_uuid' => 'min:36|max:36'
    ];

    protected array $messages = [
        'in' => 'ec5_29',
        'required' => 'ec5_20',
        'array' => 'ec5_87',
        'date' => 'ec5_79',
        'same' => 'ec5_53',
        'required_unless' => 'ec5_20',
        'required_if' => 'ec5_21',
        'min' => 'ec5_28',
        'max' => 'ec5_28',
        'present' => 'ec5_20',
        'boolean' => 'ec5_29'
    ];

    /**
     * @var RuleEntry
     */
    protected $ruleEntry;

    /**
     * @var RuleBranchEntry
     */
    protected $ruleBranchEntry;

    /**
     * @var RuleAnswers
     */
    protected $ruleAnswers;

    /**
     * @var RuleFileEntry
     */
    protected $ruleFileEntry;

    /**
     * @var bool
     */
    protected $hasAnswers;

    /**
     * RuleUpload constructor.
     * @param RuleEntry $ruleEntry
     * @param RuleBranchEntry $ruleBranchEntry
     * @param RuleAnswers $ruleAnswers
     * @param RuleFileEntry $ruleFileEntry
     */
    public function __construct(
        RuleEntry       $ruleEntry,
        RuleBranchEntry $ruleBranchEntry,
        RuleAnswers     $ruleAnswers,
        RuleFileEntry   $ruleFileEntry
    ) {
        $this->ruleEntry = $ruleEntry;
        $this->ruleBranchEntry = $ruleBranchEntry;
        $this->ruleAnswers = $ruleAnswers;
        $this->ruleFileEntry = $ruleFileEntry;
    }

    /**
     * @param $data
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @return null
     */
    public function additionalChecks($data, ProjectDTO $project, EntryStructureDTO $entryStructure): ?bool
    {
        // Implode as string so we can use regex
        $stringData = Arrays::implodeMulti($this->data);

        // If preg_match returns 1 or false, error out
        // 0 means no matches, which is the only case allowed
        // Check for HTML
        if (Strings::containsHtml($stringData)) {
            $this->errors['validation'] = ['ec5_220'];
            return false;
        }

        // Check for emoji
        if (Strings::containsEmoji($stringData)) {
            $this->errors['validation'] = ['ec5_323'];
            return false;
        }


        $projectExtra = $project->getProjectExtra();

        // Default to 'entry' validator
        $validator = $this->ruleEntry;

        /* ENTRY SPECIFIC DETAILS */

        // Set the entry-type-specific validator to use
        switch ($data['type']) {
            case config('epicollect.strings.entry_types.branch_entry'):
                $validator = $this->ruleBranchEntry;
                break;
            case config('epicollect.strings.entry_types.file_entry'):
                $validator = $this->ruleFileEntry;
                break;
        }

        /* ENTRY VALIDATION */

        $formRef = $entryStructure->getFormRef();
        $form = $projectExtra->getFormDetails($formRef);

        // Check form exists
        if (count($form) == 0) {
            $this->errors[$formRef] = ['ec5_15'];
            return false;
        }

        //Bail out if the upload entry type is invalid
        if (!$validator) {
            $this->errors['upload'] = ['ec5_52'];
            return false;
        }

        // Validate the entry values
        $entry = $entryStructure->getEntry();
        $validator->validate($entry);
        if ($validator->hasErrors()) {
            $this->errors = $validator->errors();
            return false;
        }
        // Do additional checks on all entry types
        $validator->additionalChecks($project, $entryStructure);
        if ($validator->hasErrors()) {
            $this->errors = $validator->errors();
            return false;
        }
        return true;
    }
}
