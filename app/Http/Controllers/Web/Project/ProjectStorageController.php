<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Traits\Requests\RequestAttributes;

class ProjectStorageController
{
    use RequestAttributes;

    public function show()
    {
        if (!$this->requestedProjectRole()->getUser()->isSuperAdmin()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        return view('project.project_details', [
            'includeTemplate' => 'storage',
            'action' => 'storage'
        ]);
    }
}
