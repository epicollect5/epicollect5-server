<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Services\Media\MediaCounterService;
use ec5\Traits\Requests\RequestAttributes;

class ProjectStorageController
{
    use RequestAttributes;

    public function show(MediaCounterService $mediaCounterService)
    {
        if (!$this->requestedProjectRole()->getUser()->isSuperAdmin()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        $mediaUsage = $mediaCounterService->getMediaStorageUsageFromDB(
            $this->requestedProject()->getId(),
            $this->requestedProject()->ref
        );


        return view('project.project_details', [
            'includeTemplate' => 'storage',
            'action' => 'storage',
            'mediaUsage' => $mediaUsage
        ]);
    }
}
