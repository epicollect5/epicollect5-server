<?php

namespace ec5\Http\Controllers\Web\Project;

use Response;

class ProjectDataController
{
    public function edit()
    {
        return view('project.pwa');
    }

    public function editDebug()
    {
        //kick out if in production, this route is only for debugging locally
        if (app()->isProduction()) {
            return Response::apiErrorCode(400, ['project-data-controller' => ['ec5_91']]);
        }

        return $this->edit();
    }

    public function add()
    {
        return view('project.pwa');
    }
}
