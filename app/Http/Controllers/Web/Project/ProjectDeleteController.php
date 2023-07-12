<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;
use ec5\Repositories\QueryBuilder\Project\DeleteRepository as DeleteProject;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as SearchProject;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectArchive;
use Exception;

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
    public function softDelete(SearchProject $searchProject)
    {
        if (!$this->requestedProjectRole->canDeleteProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        // Check if this project is featured, cannot be deleted
        if ($searchProject->isFeatured($this->requestedProject->getId())) {
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_221']);
        }

        try {
            DB::beginTransaction();
            //cloning project row (for potential restore, safety net)
            $project = Project::where('id', $this->requestedProject->getId())
                ->where('slug', $this->requestedProject->slug)
                ->first();
            // replicate (duplicate) the data
            $projectArchive = $project->replicate();
            $projectArchive->id = $this->requestedProject->getId();
            $projectArchive->created_at = $project->created_at;
            $projectArchive->updated_at = $project->updated_at;
            // make into array for mass assign. 
            $projectArchive = $projectArchive->toArray();
            //create copy to projects_archive table
            ProjectArchive::create($projectArchive);

            //delete original row 
            //(entries and media files are not touched)
            // they could be removed at a later stage by a background script
            $project->delete();

            DB::commit();
            //redirect to user projects
            return redirect('myprojects')->with('message', 'ec5_114');
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Cannot delete project', ['exception' => $e->getMessage()]);
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_222']);
        }
    }
}
