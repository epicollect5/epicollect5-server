<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Validation\Project\RuleName;
use ec5\Models\Project\Project;
use ec5\Services\AvatarService;
use ec5\Services\ProjectService;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Redirect;

class ProjectCloneController
{
    use RequestAttributes;

    public function show()
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        $vars['includeTemplate'] = 'clone';
        $vars['showPanel'] = 'details-edit';
        $vars['action'] = 'clone';

        return view('project.project_details', $vars);
    }

    /**
     * @param Request $request
     * @param RuleName $ruleName
     * @param ProjectService $projectService
     * @return Factory|Application|RedirectResponse|View
     */
    public function store(Request        $request,
                          RuleName       $ruleName,
                          ProjectService $projectService
    )
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        $sourceProjectId = $this->requestedProject()->getId();
        $params = $request->all();

        $cloneUsers = isset($params['clone-users']) && $params['clone-users'] == 'y';
        $params['slug'] = Str::slug($request->input('name'), '-');

        // Run validation
        $ruleName->validate($params, true);
        if ($ruleName->hasErrors()) {
            $request->flash();
            return redirect()->back()->withErrors($ruleName->errors());
        }

        // Clone into $this->requestedProject
        $clonedProject = clone($this->requestedProject());
        $clonedProject->cloneProject($params);
        // Try and create
        $clonedProjectId = $projectService->storeProject($clonedProject);
        if ($clonedProjectId === 0) {
            return Redirect::back()->withErrors(['db' => ['ec5_104']]);
        }

        // Try and clone users
        if ($cloneUsers) {
            $areRolesCloned = $projectService->cloneProjectRoles($sourceProjectId, $clonedProjectId);
            if (!$areRolesCloned) {
                // Cloning users failed
                return Redirect::back()->withErrors(['db' => ['ec5_104']]);
            }
        }

        //create project logo avatar if clone is successful
        $avatarCreator = new AvatarService();
        $wasAvatarCreated = $avatarCreator->generate($clonedProject->ref, $clonedProject->name);
        if (!$wasAvatarCreated) {
            $request->flash();
            return Redirect::to('myprojects/clone')->withErrors(['avatar' => ['ec5_348']]);
        }
        //update logo_url as we are creating an avatar placeholder
        Project::where('id', $clonedProjectId)->update([
            'logo_url' => $clonedProject->ref
        ]);
        //success
        return Redirect::to('myprojects')->with('message', 'ec5_200');
    }
}
