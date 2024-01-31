<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectLeaveController
{
    use RequestAttributes;

    public function show()
    {
        if (!$this->requestedProjectRole()->canLeaveProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        return view('project.project_leave');
    }

    public function leave(Request $request)
    {
        $payload = $request->all();
        //if missing project name, bail out
        if (empty($payload['project-name'])) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/leave')->withErrors(['ec5_103']);
        }
        $projectId = $this->requestedProject()->getId();
        $projectName = Project::where('id', $projectId)->first()->name;

        //if the project name does not match, bail out
        if ($projectName !== $payload['project-name']) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/leave')->withErrors(['ec5_21']);
        }

        //no permission to leave, bail out
        if (!$this->requestedProjectRole()->canLeaveProject()) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/leave')->withErrors(['ec5_91']);
        }
        try {
            DB::beginTransaction();
            $role = ProjectRole::where('user_id', auth()->user()->id)
                ->where('project_id', $projectId)
                ->where('role', $this->requestedProjectRole()->getRole());
            $role->delete();
            DB::commit();
            //redirect to user projects
            return redirect('myprojects')->with('message', 'ec5_396');
        } catch (\Exception $e) {
            \Log::error('leave() project failure', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return redirect('myprojects/' . $this->requestedProject()->slug . '/leave')->withErrors(['ec5_104']);
        }
    }
}
