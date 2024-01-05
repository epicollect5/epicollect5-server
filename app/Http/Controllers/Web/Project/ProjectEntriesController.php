<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\Api\ApiResponse;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use ec5\Http\Validation\Project\RuleEntryLimits;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as UpdateRepository;
use Illuminate\View\View;
use Redirect;
use ec5\Traits\Requests\RequestAttributes;
use ec5\Traits\Eloquent\StatsRefresher;

class ProjectEntriesController
{
    use RequestAttributes, StatsRefresher;

    /**
     * @var UpdateRepository
     */
    protected $updateRepository;

    public function __construct(UpdateRepository $updateRepository)
    {
        $this->updateRepository = $updateRepository;

    }

    public function show()
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        $this->refreshProjectStats($this->requestedProject());

        $vars['showPanel'] = 'details-edit';
        $vars['includeTemplate'] = 'manage-entries';
        $vars['action'] = 'manage-entries';
        $vars['projectSlug'] = $this->requestedProject()->slug;
        $vars['entries_limits_max'] = config('epicollect.limits.entries_limits_max');
        $vars['can_bulk_upload_enums'] = array_keys(config('epicollect.strings.can_bulk_upload'));
        $vars['can_bulk_upload'] = $this->requestedProject()->getCanBulkUpload();
        return view('project.project_details', $vars);
    }

    /**
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param RuleEntryLimits $ruleEntryLimits
     * @return Factory|Application|JsonResponse|RedirectResponse|View
     */
    public function store(Request $request, ApiResponse $apiResponse, RuleEntryLimits $ruleEntryLimits)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        // Get request data
        $payload = $request->all();

        // unset the csrf token
        unset($payload['_token']);
        // Validate
        if (count($payload) > 0) {
            foreach ($payload as $ref => $data) {
                // If no limit set, remove and skip
                if (!isset($data['limit']) || $data['limit'] != 1) {
                    unset($payload[$ref]);
                    continue;
                }
                // Validate each set of ref limits
                $ruleEntryLimits->validate($data);
                if ($ruleEntryLimits->hasErrors()) {
                    // Otherwise error back and prompt user to add new
                    if ($request->ajax()) {
                        return $apiResponse->errorResponse(400, $ruleEntryLimits->errors());
                    }
                    return Redirect::back()->withErrors($ruleEntryLimits->errors());
                }

                // Additional check
                $ruleEntryLimits->additionalChecks($this->requestedProject(), $ref, $data);
                if ($ruleEntryLimits->hasErrors()) {
                    // Otherwise error back and prompt user to add new
                    if ($request->ajax()) {
                        return $apiResponse->errorResponse(400, $ruleEntryLimits->errors());
                    }
                    return Redirect::back()->withErrors($ruleEntryLimits->errors());
                }
            }
        }

        // Add to project definitions
        $this->requestedProject()->setEntriesLimits($payload);

        // Update in db
        if (!$this->updateRepository->updateProjectStructure($this->requestedProject(), true)) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['manage-entries' => 'ec5_45']);
            }
            return Redirect::back()->withErrors($ruleEntryLimits->errors());
        }

        // If ajax, return success 200 code
        if ($request->ajax()) {
            return $apiResponse->toJsonResponse(200);
        }

        // Success
        return Redirect::back()->with('message', 'ec5_200');
    }
}
