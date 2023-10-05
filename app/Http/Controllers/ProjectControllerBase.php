<?php

namespace ec5\Http\Controllers;

use ec5\Models\Users\User;
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

        return [
            'includeTemplate' => $includeTemplate,
            'requestedProjectRole' => $this->requestedProjectRole,
            'project' => $this->requestedProject,
            'projectStats' => $this->requestedProject->getProjectStats(),
            'jsonPretty' => $this->getJsonPretty(),
            'jsonPrettyExtra' => $this->getJsonPrettyExtra(),
            'showPanel' => $showPanel,
            'allForms' => $this->getExtraJsonHelpers(),
            'lastEntryDate' => $this->getLastEntryDate(),
            'hasInputs' => count($this->requestedProject->getProjectExtra()->getInputs()) != 0
        ];
    }

    /**
     * return pretty json for blade templates
     * @return string
     */
    protected function getJsonPretty(): string
    {
        return json_encode($this->requestedProject->getProjectDefinition()->getData(), JSON_PRETTY_PRINT);
    }

    /**
     * return pretty json for blade templates
     * @return string
     */
    protected function getJsonPrettyExtra(): string
    {
        return json_encode($this->requestedProject->getProjectExtra()->getData(), JSON_PRETTY_PRINT);
    }

    /**
     * Return array of all forms for var in blade template
     * @return array
     */
    protected function getExtraJsonHelpers(): array
    {
        return $this->requestedProject->getProjectExtra()->getForms();
    }

    /**
     * Return string of last entry date from looping form-counts
     *
     * @return string
     */
    protected function getLastEntryDate(): string
    {

        // Set current entry date as 0
        $currentDate = 0;

        $projectDefinition = $this->requestedProject->getProjectStats()->getData();
        $formCounts = $projectDefinition['form_counts'];

        if (count($formCounts) == 0) {
            return '';
        }

        foreach ($formCounts as $formRef => $values) {

            if (empty($values['last_entry_created'])) {
                continue;
            }

            // Parse into unix timestamp
            $currentFormDate = strtotime($values['last_entry_created']);

            // Check if we have a current form date greater than the current date
            if ($currentFormDate > $currentDate) {
                $currentDate = $currentFormDate;
            }
        }

        return $currentDate > 0 ? $currentDate : '';
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
