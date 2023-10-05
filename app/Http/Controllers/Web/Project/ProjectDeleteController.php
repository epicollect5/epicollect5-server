<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository;
use Illuminate\Http\Request;
use ec5\Repositories\QueryBuilder\Project\DeleteRepository as DeleteProject;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as SearchProject;
use Illuminate\Support\Facades\DB;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\ProjectFeatured;

class ProjectDeleteController extends ProjectControllerBase
{
    protected $errors = [];

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function show()
    {
        if (!$this->requestedProjectRole->canDeleteProject()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $vars = $this->defaultProjectDetailsParams('', '', true);

        return view('project.project_delete', $vars);
    }

    public function delete(DeleteProject $deleteProject, SearchProject $searchProject)
    {
        if (!$this->requestedProjectRole->canDeleteProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        // Check if this project is featured, cannot be deleted
        if ($searchProject->isFeatured($this->requestedProject->getId())) {
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_221']);
        }
        // Attempt to delete the project and all data
        $deleteProject->delete($this->requestedProject->getId());
        // If the delete fails, error out
        if ($deleteProject->hasErrors()) {
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors($deleteProject->errors());
        }
        // Attempt to delete all project media
        $deleteProject->deleteProjectMedia($this->requestedProject->ref);
        // If the delete media fails, inform user
        // Project has already been deleted by this point
        if ($deleteProject->hasErrors()) {
            return redirect('myprojects/')->withErrors($deleteProject->errors());
        }
        // Succeeded
        return redirect('myprojects')->with('message', 'ec5_114');
    }

    /*
    Soft delete a project by moving its row to an archive table.
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
    */
    public function softDelete()
    {
        $projectId = $this->requestedProject->getId();
        $projectSlug = $this->requestedProject->slug;
        //no permission to delete, bail out
        if (!$this->requestedProjectRole->canDeleteProject()) {
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_91']);
        }
        // Check if this project is featured, cannot be deleted
        if (ProjectFeatured::where('project_id', $projectId)->exists()) {
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_221']);
        }

        try {
            DB::beginTransaction();
            if (!$this->archiveProject($projectId, $projectSlug)) {
                throw new \Exception('Project archive failed');
            }

            DB::commit();
            //redirect to user projects
            return redirect('myprojects')->with('message', 'ec5_114');
        } catch (\Exception $e) {
            \Log::error('softDelete() project failure', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_104']);
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
