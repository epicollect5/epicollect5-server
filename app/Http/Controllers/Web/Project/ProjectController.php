<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Config;
use Uuid;

class ProjectController extends ProjectControllerBase
{
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * ProjectController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Show Project home page
     *
     * @return $this|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show()
    {
        $vars = $this->defaultProjectDetailsParams('', '',  true);
        $canShowSocialMediaShareBtns = false;
        $PUBLIC = Config::get('ec5Strings.project_access.public');
        $LISTED = Config::get('ec5Strings.project_visibility.listed');

        if ($vars['project']->access === $PUBLIC && $vars['project']->visibility === $LISTED) {
            $canShowSocialMediaShareBtns = true;
        }
        $vars['canShowSocialMediaShareBtns'] = $canShowSocialMediaShareBtns;

        // If the project is trashed, redirect to error page
        if ($this->requestedProject->status == Config::get('ec5Strings.project_status.trashed')) {
            return view('errors.gen_error')->withErrors(['view' => 'ec5_202']);
        }

        //HACK FOR COG-UK: stop users not logged in
        if ($this->requestedProject->ref === '293a6f6a46ea438d8940e102acb008e4') {
            if (!$this->requestedProjectRole->canEditData()) {
                return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
            }
        }
        //END HACK


        return view('project.project_home', $vars);
    }

    /**
     * Show a Project details
     *
     * @return $this|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function details()
    {
        if (!$this->requestedProjectRole->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        // IncludeTemplate, showPanel
        $vars = $this->defaultProjectDetailsParams('view', 'details-view');

        return view('project.project_details', $vars);
    }

    /**
     * Download the project structure JSON
     */
    public function downloadStructure()
    {
        return new JsonResponse(
            [
                'meta' => [
                    'project_mapping' => $this->requestedProject->getProjectMapping()->getData()
                ],
                'data' => $this->requestedProject->getProjectDefinition()->getData()
            ],
            200,
            [
                'Content-disposition' => 'attachment; filename=' . $this->requestedProject->slug . '.json',
                'Content-type' => 'text/plain'
            ]
        );
    }

    /**
     * Show the form for editing the specified resource.
     * NO POSTS ALLOWED should only be get to show data
     *
     * @param  \Illuminate\Http\Request $request
     * @param  slug
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $slug)
    {
        $action = last($request->segments());

        if (!$this->requestedProjectRole->canEditProject()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        if ($action == 'formbuilder') {
            return view(
                'project.formbuilder',
                ['projectName' => $this->requestedProject->name]
            );
        }

        $vars = $this->defaultProjectDetailsParams('edit', 'details-edit');
        $vars['action'] = $action;

        return view('project.project_details', $vars);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $slug project -> slug
     * @return \Illuminate\Http\Response
     */
    public function data(Request $request, $slug)
    {
        //get project name
        //HACK FOR COG-UK: stop users not logged in
        if ($this->requestedProject->ref === '293a6f6a46ea438d8940e102acb008e4') {
            if (!$this->requestedProjectRole->canEditData()) {
                return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
            }
        }
        //END HACK

        return view('project.dataviewer', [
            'project' => $this->requestedProject
        ]);
    }
}
