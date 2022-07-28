<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Config;
use Uuid;

class ProjectDataController extends ProjectControllerBase
{

    /**
     * ProjectController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function edit()
    {
        return view('project.data_editor',
            []
        );
    }

    public function add()
    {


        return view('project.data_editor',
            []
        );
    }

}