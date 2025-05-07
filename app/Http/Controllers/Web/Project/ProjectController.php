<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Traits\Eloquent\StatsRefresher;
use ec5\Traits\Requests\RequestAttributes;
use Response;

class ProjectController
{
    use StatsRefresher;
    use RequestAttributes;

    public function show()
    {
        $this->refreshProjectStats($this->requestedProject());
        $vars = [];

        // If the project is trashed, redirect to error page
        if ($this->requestedProject()->status == config('epicollect.strings.project_status.trashed')) {
            return view('errors.gen_error')->withErrors(['view' => 'ec5_11']);
        }

        /**
         * @var $projectStats ProjectStats
         */
        //get latest entry timestamp
        $projectStats = ProjectStats::where('project_id', $this->requestedProject()->getId())->first();
        $vars['mostRecentEntryTimestamp'] = $projectStats->getMostRecentEntryTimestamp();

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
        return Response::toJSONFile(
            ['data' => $this->requestedProject()->getProjectDefinition()->getData()],
            $this->requestedProject()->slug . '.json'
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

        $totalEntries = ProjectStats::where('project_id', $this->requestedProject()->getId())->value('total_entries');

        return view('project.formbuilder', ['totalEntries' => $totalEntries]);
    }

    /*
     * Show dataviewer page
     */
    public function dataviewer()
    {
        // If the project is trashed, redirect to error page
        if ($this->requestedProject()->status === config('epicollect.strings.project_status.trashed')) {
            return view('errors.gen_error')->withErrors(['view' => 'ec5_11']);
        }

        $this->refreshProjectStats($this->requestedProject());
        return view('project.dataviewer', [
            'project' => $this->requestedProject()
        ]);
    }

    //open the project in app page
    public function open()
    {
        //show the project open page with the open-in-app banner
        $params = [];
        // If the project is trashed, redirect to error page
        if ($this->requestedProject()->status === config('epicollect.strings.project_status.trashed')) {
            return view('errors.gen_error')->withErrors(['view' => 'ec5_11']);
        }

        return view('project.project_open', $params);
    }
}
