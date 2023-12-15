<?php

namespace ec5\Http\Controllers\Web\Project;

use Illuminate\Http\Request;
use ec5\Models\Projects\Project;
use ec5\Http\Validation\Project\RuleName as Validator;
use ec5\Repositories\QueryBuilder\Project\CreateRepository as CreateProject;
use ec5\Repositories\QueryBuilder\ProjectRole\CreateRepository as CreateProjectRole;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as UpdateRep;
use ec5\Models\Images\CreateProjectLogoAvatar;
use Illuminate\Support\Str;
use Redirect;
use ec5\Traits\Project\ProjectBundle;

class ProjectCloneController
{
    use ProjectBundle;

    protected $project;
    protected $updateRep;

    public function __construct(Project $project, UpdateRep $updateRep)
    {
        $this->project = $project;
        $this->updateRep = $updateRep;
    }

    public function show()
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $vars = $this->defaultProjectDetailsParams('clone', 'details-edit');
        $vars['action'] = 'clone';

        return view('project.project_details', $vars);
    }

    /**
     * @param Request $request
     * @param Validator $validator
     * @param CreateProject $createProject
     * @param CreateProjectRole $createProjectRole
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, Validator $validator, CreateProject $createProject, CreateProjectRole $createProjectRole)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $oldProjectId = $this->requestedProject()->getId();

        // Get input
        $params = $request->all();

        $cloneUsers = isset($params['clone-users']) && $params['clone-users'] == 'y' ? true : false;
        $params['slug'] = Str::slug($request->input('name'), '-');

        // Run validation
        $validator->validate($params, true);
        if ($validator->hasErrors()) {
            $request->flash();
            return redirect()->back()->withErrors($validator->errors());
        }

        // Clone into $this->requestedProject
        $clonedProject = clone($this->requestedProject());
        $clonedProject->cloneProject($params);

        // Try and create, else return DB errors
        $projectId = $createProject->create($clonedProject);

        if ($projectId === 0) {
            // Return db create errors
            return Redirect::back()->withErrors(['db' => $createProject->errors()]);
        }

        // Try and clone users
        if ($cloneUsers) {
            $tryCloneUsers = $createProjectRole->cloneProjectRoles($oldProjectId, $createProject->getProjectId());
            if (!$tryCloneUsers) {
                // Cloning users failed
                return Redirect::back()->withErrors(['db' => $createProjectRole->errors()]);
            }
        }

        //create project logo avatar
        if ($projectId > 0) {
            //set the newly generated project ID in the model in memory
            $this->project->setId($projectId);
            //generate project logo avatar(s)
            $avatarCreator = new CreateProjectLogoAvatar();
            $wasCreated = $avatarCreator->generate($clonedProject->ref, $clonedProject->name);

            if ($wasCreated) {
                unset($params);
                //update logo_url as we are creating an avatar placeholder
                $params['logo_url'] = $clonedProject->ref;

                if ($this->doUpdate($params)) {
                    return Redirect::to('myprojects')->with('message', 'ec5_200');
                } else {
                    // Return db update errors
                    $request->flash();
                    return Redirect::to('myprojects/clone')->withErrors(['db' => ['ec5_104']]);
                }
            } else {
                //error generating project avatar, handle it!
                // Return db create errors
                $request->flash();
                return Redirect::to('myprojects/clone')->withErrors(['avatar' => ['ec5_348']]);
            }
        }
        // Success
        return Redirect::to('myprojects')->with('message', 'ec5_200');
    }

    /**
     * Update the project in db
     *
     * @param $params
     * @param bool $updateProjectStructuresTable
     * @return bool
     */
    private function doUpdate($params)
    {
        // Update the Definition and Extra data
        $this->project->updateProjectDetails($params);
        // Update in the database
        return $this->updateRep->updateProject($this->project, $params, false);
    }
}
