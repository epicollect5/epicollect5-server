<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectFeatured;
use ec5\Models\Project\ProjectStats;
use ec5\Traits\Eloquent\Archiver;
use ec5\Traits\Eloquent\StatsRefresher;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Log;

class ProjectDeleteController
{
    use RequestAttributes, Archiver, StatsRefresher;

    public function show()
    {
        if (!$this->requestedProjectRole()->canDeleteProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        $this->refreshProjectStats($this->requestedProject());
        return view('project.project_delete');
    }

    public function delete(Request $request)
    {
        $payload = $request->all();
        $projectSlug = $this->requestedProject()->slug;

        //if missing project name, bail out
        if (empty($payload['project-name'])) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')->withErrors(['ec5_103']);
        }
        $projectId = $this->requestedProject()->getId();
        $projectName = Project::where('id', $projectId)->first()->name;

        //if the project name does not match, bail out
        if ($projectName !== $payload['project-name']) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')->withErrors(['ec5_21']);
        }
        //no permission to delete, bail out
        if (!$this->requestedProjectRole()->canDeleteProject()) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')->withErrors(['ec5_91']);
        }
        // Check if this project is featured, cannot be deleted
        if (ProjectFeatured::where('project_id', $projectId)->exists()) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')->withErrors(['ec5_221']);
        }

        $projectStat = ProjectStats::where('project_id', $projectId)->first();
        if ($projectStat->total_entries === 0) {
            if ($this->hardDelete($projectId, $projectSlug)) {
                return redirect('myprojects')->with('message', 'ec5_114');
            } else {
                return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')
                    ->withErrors(['ec5_104']);
            }
        } else {
            if ($this->softDelete($projectId, $projectSlug)) {
                return redirect('myprojects')->with('message', 'ec5_114');
            } else {
                return redirect('myprojects/' . $this->requestedProject()->slug . '/delete')
                    ->withErrors(['ec5_104']);
            }
        }
    }

    /*
    Soft delete a project by setting its status to archived
    Entries and branch entries are not touched.
    Media files for the project are not touched, they can be removed at a later stage
    since deleting lots of rows and files is an expensive operation
    and the user is waiting.
    The following tables have a FK to the project table with ON CASCADE DELETE:
    - project_structures,
    - project_stats,
    - project_roles,
    - oauth_client_projects,
    - project_datasets

    None of them get touched
    */
    public function softDelete($projectId, $projectSlug)
    {
        return $this->archiveProject($projectId, $projectSlug);
    }

    /*hard delete a project
     The following tables have a FK to the project table with ON CASCADE DELETE:
    - project_structures,
    - project_stats,
    - project_roles,
    - oauth_client_projects,
    - project_datasets
    */
    public function hardDelete($projectId, $projectSlug)
    {
        try {
            DB::beginTransaction();
            //project must have trashed status
            $trashedStatus = config('epicollect.strings.project_status.trashed');
            $project = Project::where('id', $projectId)
                ->where('slug', $projectSlug)
                ->where('status', $trashedStatus);

            if ($project->delete()) {
                DB::commit();
                return true;
            } else {
                DB::rollBack();
                return false;
            }
        } catch (\Exception $e) {
            Log::error('hardDelete() project failure', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

//delete entries in chunks (branch entries are deleted by FK constraint ON CASCADE DELETE)
    private function deleteEntries($projectId)
    {
        Entry::where('project_id', $projectId)->chunk(100, function ($rows) {
            foreach ($rows as $row) {
                if (!$row->delete()) {
                    throw new \Exception('Entry deletion failed');
                }
            }
        });
    }
}
