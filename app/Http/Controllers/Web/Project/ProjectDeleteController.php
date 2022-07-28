<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;
use ec5\Repositories\QueryBuilder\Project\DeleteRepository as DeleteProject;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as SearchProject;

class ProjectDeleteController extends ProjectControllerBase
{
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
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show()
    {
        if (!$this->requestedProjectRole->canDeleteProject()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $vars = $this->defaultProjectDetailsParams('', '', true);

        return view('project.project_delete', $vars);
    }

    /**
     * @param DeleteProject $deleteProject
     * @param SearchProject $searchProject
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function delete(DeleteProject $deleteProject, SearchProject $searchProject)
    {

        if (!$this->requestedProjectRole->canDeleteProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        // Check if this project is featured
        if ($searchProject->isFeatured($this->requestedProject->getId())) {
            return redirect('myprojects/' . $this->requestedProject->slug)->withErrors(['ec5_221']);
        }

        /* DELETING */

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
}
