<?php

namespace ec5\Traits\Project;

use ec5\Models\Project\Project;
use ec5\Services\Project\ProjectAvatarService;
use Log;
use Throwable;

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
            } catch (Throwable $e) {
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
