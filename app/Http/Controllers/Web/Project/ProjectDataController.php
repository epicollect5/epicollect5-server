<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Traits\Requests\RequestAttributes;

class ProjectDataController
{
    use RequestAttributes;

    public function edit()
    {
        if (config('epicollect.setup.pwa_enabled')) {
            return view('project.pwa', [
                'projectName' => $this->requestedProject()->name
            ]);
        }

        return view('project.data_editor');
    }

    public function add()
    {
        if (config('epicollect.setup.pwa_enabled')) {
            return view('project.pwa', [
                'projectName' => $this->requestedProject()->name
            ]);
        }

        return view('project.data_editor');
    }
}
