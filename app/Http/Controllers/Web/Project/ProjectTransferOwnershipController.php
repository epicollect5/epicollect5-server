<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Models\Eloquent\Project;
use ec5\Services\ProjectService;
use Illuminate\Http\Request;
use ec5\Http\Validation\Project\RuleTransferOwnership as TransferValidator;
use Auth;
use ec5\Traits\Requests\RequestAttributes;

class ProjectTransferOwnershipController
{
    use RequestAttributes;

    /**
     * @var array
     */
    protected $errors = [];

    public function show(ProjectService $projectService)
    {
        $options['roles'] = ['manager', 'creator'];
        // Set project id in options
        $options['project_id'] = $this->requestedProject()->getId();
        //stop non-creators from performing this action
        if (!$this->requestedProjectRole()->isCreator()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        // Get paginated project users, based on per page, specified roles, current page and search term
        //here we assume there are never more than 1000 managers for a project.
        $projectMembers = $projectService->getProjectMembersPaginated(1000, '', $options);
        //need to grab "manager" key as repository returns grouped by roles
        $vars['projectManagers'] = $projectMembers['manager'];
        $vars['projectCreator'] = $projectMembers['creator'][0];

        return view('project.project_transfer_ownership', $vars);

    }

    public function transfer(Request $request, TransferValidator $transferValidator)
    {
        //if the current logged-in user is not a creator for the project, abort
        if (!$this->requestedProjectRole()->isCreator()) {
            return redirect()->back()->withErrors(['errors' => ['ec5_91']]);
        }

        //check manager value is valid
        $input['manager'] = $request->manager;
        $transferValidator->validate($input);

        if ($transferValidator->hasErrors()) {
            return redirect()->back()->withErrors($transferValidator->errors());
        }

        //this is the current logged-in user as he is the only one who has got access to this feature
        $creatorId = Auth::user()->id;
        $managerId = $input['manager'];
        $projectId = $this->requestedProject()->getId();
        $project = new Project();

        if ($project->transferOwnership($projectId, $creatorId, $managerId)) {
            //redirect back with the success message (to manage user page)
            return redirect()
                ->route('manage-users', ['project_slug' => $this->requestedProject()->slug])
                ->with('message', 'ec5_331');
        } else {
            //show error back to user if any fails
            return redirect()->back()->withErrors(['errors' => ['ec5_104']]);
        }
    }
}