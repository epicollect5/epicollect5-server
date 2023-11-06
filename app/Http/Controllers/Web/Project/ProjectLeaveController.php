<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use ec5\Models\Eloquent\ProjectRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectLeaveController extends ProjectControllerBase
{

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function show()
    {
        if (!$this->requestedProjectRole->canLeaveProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        $vars = $this->defaultProjectDetailsParams('', '', true);
        return view('project.project_leave', $vars);
    }

    public function leave()
    {
        $projectId = $this->requestedProject->getId();
        //no permission to leave, bail out
        if (!$this->requestedProjectRole->canLeaveProject()) {
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_91']);
        }
        try {
            DB::beginTransaction();
            $role = ProjectRole::where('user_id', auth()->user()->id)
                ->where('project_id', $projectId)
                ->where('role', $this->requestedProjectRole->getRole());
            $role->delete();
            DB::commit();
            //redirect to user projects
            return redirect('myprojects')->with('message', 'ec5_396');
        } catch (\Exception $e) {
            \Log::error('leave() project failure', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_104']);
        }
    }
}
