<?php

namespace ec5\Http\Controllers;

use ec5\Models\Eloquent\ProjectStat;
use ec5\Models\Eloquent\User;
use Illuminate\Http\Request;

use ec5\Models\ProjectRoles\ProjectRole;
use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as SearchProjectRepository;
use ec5\Traits\Eloquent\Archiver;

class ProjectControllerBase extends Controller
{
    use Archiver;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Project
     */
    protected $requestedProject;

    /**
     * @var User
     */
    protected $requestedUser;

    /**
     * @var ProjectRole
     */
    protected $requestedProjectRole;

    /**
     * Ec5ProjectBaseController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->middleware(function ($request, $next) {
            $this->request = $request;
            $this->requestedProject = $request->attributes->get('requestedProject');
            $this->requestedProjectRole = $request->attributes->get('requestedProjectRole');
            $this->requestedUser = $request->attributes->get('requestedUser');

            return $next($request);
        });
    }

    /**
     * Common project template params for details. editing etc
     *
     * @param $includeTemplate
     * @param string $showPanel
     * @return array
     */
    protected function defaultProjectDetailsParams($includeTemplate, $showPanel = '', $needStatsUpdate = false): array
    {
        //update project stats when needed
        if ($needStatsUpdate) {
            $this->refreshProjectStats();
        }

        $smallDescriptionSpecs = config('ec5Limits.project.small_desc.min') . ' to ' . config('ec5Limits.project.small_desc.max') . ' chars';
        $descriptionSpecs = config('ec5Limits.project.description.min') . ' to ' . config('ec5Limits.project.description.max') . ' chars';
        $projectDefinitionPrettyPrint = json_encode($this->requestedProject->getProjectDefinition()->getData(), JSON_PRETTY_PRINT);
        $projectExtraPrettyPrint = json_encode($this->requestedProject->getProjectExtra()->getData(), JSON_PRETTY_PRINT);
        $projectStats = ProjectStat::where('project_id', $this->requestedProject->getId())->first();

        return [
            'includeTemplate' => $includeTemplate,
            'requestedProjectRole' => $this->requestedProjectRole,
            'project' => $this->requestedProject,
            'projectStats' => $this->requestedProject->getProjectStats(),
            'projectDefinitionPrettyPrint' => $projectDefinitionPrettyPrint,
            'projectExtraPrettyPrint' => $projectExtraPrettyPrint,
            'showPanel' => $showPanel,
            'mostRecentEntryTimestamp' => $projectStats->getMostRecentEntryTimestamp(),
            'hasInputs' => count($this->requestedProject->getProjectExtra()->getInputs()) > 0,
            'smallDescriptionSpecs' => $smallDescriptionSpecs,
            'descriptionSpecs' => $descriptionSpecs
        ];
    }

    private function refreshProjectStats()
    {
        $searchProjectLegacy = new SearchProjectRepository();
        $entryStatsRepository = new StatsRepository();
        $entryStatsRepository->updateProjectEntryStats($this->requestedProject);

        // Retrieve project with updated stats (legacy way, R&A fiasco)
        $project = $searchProjectLegacy->find($this->requestedProject->slug);
        if ($project) {
            // Refresh the main Project model
            $this->requestedProject->init($project);
        }
    }
}
