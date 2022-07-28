<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use ec5\Models\Eloquent\Project;
use Illuminate\Http\Request;

use ec5\Repositories\QueryBuilder\ProjectRole\SearchRepository as ProjectRoleSearch;
use ec5\Http\Validation\Project\RuleTransferOwnership as TransferValidator;

use Auth;
use Illuminate\Support\Facades\Config;
use Exception;
use DB;
use Log;
use ec5\Libraries\EC5Logger\EC5Logger;

class ProjectTransferOwnershipController extends ProjectControllerBase
{

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var ProjectRoleSearch object
     */
    protected $projectRoleSearch;


    /**
     * ProjectController constructor.
     * @param Request $request
     */
    public function __construct(ProjectRoleSearch $projectRoleSearch, Request $request)
    {
        parent::__construct($request);
        $this->projectRoleSearch = $projectRoleSearch;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show()
    {
        $options['roles'] = ['manager', 'creator'];
        // Set project id in options
        $options['project_id'] = $this->requestedProject->getId();

        //stop non-creators from performing this action
        if (!$this->requestedProjectRole->isCreator()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $vars = $this->defaultProjectDetailsParams('', '');

        // Get paginated project users, based on per page, specified roles, current page and search term
        //here we assume there are never more than 1000 managers for a project.
        $users = $this->projectRoleSearch->paginate(1000, 1, '', $options);
        //need to grab "manager" key as repository returns grouped by roles
        $vars['projectManagers'] = $users['manager'];
        $vars['projectCreator'] = $users['creator'][0];

        return view('project.project_transfer_ownership', $vars);

    }

    public function transfer(Request $request, TransferValidator $tranferValidator)
    {
        //if the current logged in user is not a creator for the project, abort
        if (!$this->requestedProjectRole->isCreator()) {
            return redirect()->back()->withErrors(['errors' => ['ec5_91']]);
        }

        //check manager value is valid
        $input['manager'] = $request->manager;
        $tranferValidator->validate($input);

        if ($tranferValidator->hasErrors()) {
            return redirect()->back()->withErrors($tranferValidator->errors());
        }

        //this is the current logged in user as he is the only one whio has got access to this feature
        $creatorId = Auth::user()->id;
        $managerId = $input['manager'];
        $projectId = $this->requestedProject->getId();
        $project = new Project();

        if ($project->transferOwnership($projectId, $creatorId, $managerId)) {
            //redirect back with success message (to manage user page)
            return redirect()
                ->route('manage-users', ['project_slug' => $this->requestedProject->slug])
                ->with('message', 'ec5_331');
        } else {
            //show error back to user if any fails
            return redirect()->back()->withErrors(['errors' => ['ec5_104']]);
        }
    }
}