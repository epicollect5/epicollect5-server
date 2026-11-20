<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Traits\Requests\RequestAttributes;

class ProjectDataController
{
    use RequestAttributes;

    public function edit()
    {
        return view('project.data_editor');
    }

    public function add()
    {
        return view('project.data_editor');
    }

    public function pwaEdit()
    {
        return view('project.pwa', [
            'projectName' => $this->requestedProject()->name
        ]);
    }

    public function pwaAdd()
    {
        return view('project.pwa', [
            'projectName' => $this->requestedProject()->name
        ]);
    }
}
