<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;
use ec5\Repositories\QueryBuilder\Project\DeleteRepository as DeleteProject;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as SearchProject;
use Illuminate\Support\Facades\DB;

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

        // Check if this project is featured
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

    //soft delete a project by moving row to archive table
    //entries and branch entries are moved to archive tables
    //media files for the project are not touched, they can be removed at a later stage
    //since deleting lots of file is an expensive operation
    public function softDelete(SearchProject $searchProject)
    {
        if (!$this->requestedProjectRole->canDeleteProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        // Check if this project is featured, cannot be deleted
        if ($searchProject->isFeatured($this->requestedProject->getId())) {
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_221']);
        }

        DB::beginTransaction();
        if ($this->archiveProject() && $this->archiveEntries()) {
            //all good
            DB::commit();
            //redirect to user projects
            return redirect('myprojects')->with('message', 'ec5_114');
        } else {
            DB::rollBack();
            return redirect('myprojects/' . $this->requestedProject->slug . '/manage-entries')->withErrors(['ec5_104']);
        }
    }
}
