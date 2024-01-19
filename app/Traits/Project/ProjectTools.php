<?php

namespace ec5\Traits\Project;

use ec5\Models\Eloquent\Project;
use ec5\Models\Images\CreateProjectLogoAvatar;
use Exception;
use Log;

trait ProjectTools
{
    public function createProjectAvatar($projectId, $projectRef, $projectName): array
    {
        //generate avatar
        $avatarCreator = new CreateProjectLogoAvatar();
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
}