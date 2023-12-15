<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Traits\Requests\RequestAttributes;

class ProjectApiController
{
    use RequestAttributes;

    /**
     */
    public function show()
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        return view('project.project_details', [
            'action' => 'api',
            'includeTemplate' => 'api'
        ]);
    }
}