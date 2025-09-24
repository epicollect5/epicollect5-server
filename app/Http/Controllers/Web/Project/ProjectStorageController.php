<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Models\Project\ProjectStats;
use ec5\Traits\Requests\RequestAttributes;

class ProjectStorageController
{
    use RequestAttributes;

    public function show()
    {
        if (!$this->requestedProjectRole()->getUser()->isSuperAdmin()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        $mediaUsage = ProjectStats::where('project_id', $this->requestedProject()->getId())
            ->first()
            ->getMediaStorageUsage();

        return view('project.project_details', [
            'includeTemplate' => 'storage',
            'action' => 'storage',
            'mediaUsage' => $mediaUsage
        ]);
    }
}
