<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Models\Eloquent\ProjectStats;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ec5\Traits\Eloquent\Archiver;

class ProjectDeleteEntriesController
{
    use RequestAttributes, Archiver;

    protected $errors = [];

    public function show()
    {
        if (!$this->requestedProjectRole()->canDeleteEntries()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $projectStats = ProjectStats::where('project_id', $this->requestedProject()->getId())->first();
        return view('project.project_delete_entries', [
            'project' => $this->requestedProject(),
            'totalEntries' => $projectStats->total_entries
        ]);
    }

    /**
     * Entries and branch entries are archived
     * by copying them to archive tables.
     *
     * For performances, media files are not touched.
     * They can be deleted later by a background task (todo)
     */
    public function softDelete(
        Request      $request,
        ProjectStats $projectStats
    )
    {
        $projectName = $request->input('project-name');

        //no project name passed?
        if (!isset($projectName)) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        //if we are sending the wrong project name, bail out
        if (trim($this->requestedProject()->name) !== $projectName) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/manage-entries')
                ->withErrors(['errors' => ['ec5_91']]);
        }

        //do we have the right permissions?
        if (!$this->requestedProjectRole()->canDeleteEntries()) {
            return redirect('myprojects/' . $this->requestedProject()->slug . '/manage-entries')
                ->withErrors(['errors' => ['ec5_91']]);
        }

        try {
            DB::beginTransaction();

            if (!$this->archiveEntries($this->requestedProject()->getId())) {
                DB::rollBack();
                return redirect('myprojects/' . $this->requestedProject()->slug . '/manage-entries')->withErrors(['ec5_104']);
            } else {
                if (!$projectStats->updateProjectStats($this->requestedProject()->getId())) {
                    DB::rollBack();
                    return redirect('myprojects/' . $this->requestedProject()->slug . '/manage-entries')->withErrors(['ec5_94']);
                }
            }
            // Success!
            DB::commit();
            return redirect('myprojects/' . $this->requestedProject()->slug . '/manage-entries')->with('message', 'ec5_122');
        } catch (\Exception $e) {
            \Log::error('Error softDelete() entries', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return redirect('myprojects/' . $this->requestedProject()->slug . '/manage-entries')->withErrors(['ec5_104']);
        }
    }
}
