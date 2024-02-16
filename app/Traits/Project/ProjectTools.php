<?php

namespace ec5\Traits\Project;

use ec5\Models\Project\Project;
use ec5\Services\Mapping\DataMappingService;
use ec5\Services\Project\ProjectAvatarService;
use Exception;
use Log;

trait ProjectTools
{
    public function createProjectAvatar($projectId, $projectRef, $projectName): array
    {
        //generate avatar
        $avatarCreator = new ProjectAvatarService();
        $wasAvatarCreated = $avatarCreator->generate($projectRef, $projectName);
        if (!$wasAvatarCreated) {
            //delete project just created
            //here we assume the deletion cannot fail!!!
            try {
                Project::where('id', $projectId)->delete();
                //error generating project avatar, handle it!
                return ['avatar' => ['ec5_348']];
            } catch (Exception $e) {
                Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
                return ['db' => ['ec5_104']];
            }
        }
        //update logo_url as we are creating an avatar placeholder
        if (Project::where('id', $projectId)->update([
            'logo_url' => $projectRef
        ])) {
            return [];
        } else {
            return ['db' => ['ec5_104']];
        }
    }

    /**
     * @param $forms
     * @param $formRef
     * @return array
     *
     * Used to get a list of top level inputs and groups
     * as a flat list, skipping branch inputs
     * @see DataMappingService::setupMapping()
     */
    public function getInputsFlattened($forms, $formRef): array
    {
        $inputs = [];
        $flattenInputs = [];
        foreach ($forms as $form) {
            if ($form['ref'] === $formRef) {
                $inputs = $form['inputs'];
            }
        }

        //todo: where is the readme skipped?
        //imp: it is skipped when saving the entry payload to the DB
        foreach ($inputs as $input) {
            if ($input['type'] == config('epicollect.strings.inputs_type.group')) {
                foreach ($input['group'] as $groupInput) {
                    $flattenInputs[] = $groupInput;
                }
            } else {
                $flattenInputs[] = $input;
            }
        }
        return $flattenInputs;
    }


}