<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;
use Redirect;
use Config;

class ProjectApiController extends ProjectControllerBase
{
    /**
     * ProjectAppsController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function show(Request $request)
    {
        if (!$this->requestedProjectRole->canEditProject()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $params = $this->defaultProjectDetailsParams('api', 'details-edit');
        $params['action'] = 'api';

        return view('project.project_details', $params);

    }

}