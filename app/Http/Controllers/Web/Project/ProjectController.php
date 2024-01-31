<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Traits\Eloquent\StatsRefresher;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\JsonResponse;

class ProjectController
{

    use StatsRefresher, RequestAttributes;

    public function show()
    {
        $this->refreshProjectStats($this->requestedProject());
        $vars = [];
        $canShowSocialMediaShareBtns = false;
        $public = config('epicollect.strings.project_access.public');
        $listed = config('epicollect.strings.project_visibility.listed');

        if ($this->requestedProject()->access === $public && $this->requestedProject()->visibility === $listed) {
            $canShowSocialMediaShareBtns = true;
        }
        $vars['canShowSocialMediaShareBtns'] = $canShowSocialMediaShareBtns;

        // If the project is trashed, redirect to error page
        if ($this->requestedProject()->status == config('epicollect.strings.project_status.trashed')) {
            return view('errors.gen_error')->withErrors(['view' => 'ec5_202']);
        }


        /**
         * @var $projectStats ProjectStats
         */
        //get latest entry timestamp
        $projectStats = ProjectStats::where('project_id', $this->requestedProject()->getId())->first();
        $vars['mostRecentEntryTimestamp'] = $projectStats->getMostRecentEntryTimestamp();

        if (auth()->user()->server_role == config('epicollect.strings.server_roles.superadmin')) {
            $creatorEmail = Project::creatorEmail($this->requestedProject()->getId());
            $vars['creatorEmail'] = $creatorEmail;
        }
        return view('project.project_home', $vars);
    }

    /**
     * Show a Project details
     */
    public function details()
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        $creatorEmail = '';
        if (auth()->user()->server_role == config('epicollect.strings.server_roles.superadmin')) {
            $creatorEmail = Project::creatorEmail($this->requestedProject()->getId());
        }

        return view('project.project_details', [
            'includeTemplate' => 'view',
            'showPanel' => 'details-view',
            'creatorEmail' => $creatorEmail
        ]);
    }

    /**
     * Download the project definition as JSON
     */
    public function downloadProjectDefinition()
    {
        //todo: we have an attachment macro?
        return new JsonResponse(
            ['data' => $this->requestedProject()->getProjectDefinition()->getData()],
            200,
            [
                'Content-disposition' => 'attachment; filename=' . $this->requestedProject()->slug . '.json',
                'Content-type' => 'text/plain'
            ]
        );
    }

    /**
     * Show formbuilder page
     */
    public function formbuilder()
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        return view('project.formbuilder');
    }

    /*
     * Show dataviewer page
     */
    public function dataviewer()
    {
        $this->refreshProjectStats($this->requestedProject());
        return view('project.dataviewer', [
            'project' => $this->requestedProject()
        ]);
    }
}
