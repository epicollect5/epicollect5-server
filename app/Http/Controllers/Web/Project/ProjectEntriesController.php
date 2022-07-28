<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;

use ec5\Http\Validation\Project\RuleEntryLimits as EntryLimitsValidator;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as UpdateRepository;
use Redirect;

class ProjectEntriesController extends ProjectControllerBase
{
    /**
     * @var UpdateRepository
     */
    protected $updateRepository;

    public function __construct(Request $request, UpdateRepository $updateRepository)
    {
        $this->updateRepository = $updateRepository;

        parent::__construct($request);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show()
    {

        if (!$this->requestedProjectRole->canEditProject()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $vars = $this->defaultProjectDetailsParams('manage-entries', 'details-edit', true);
        $vars['action'] = 'manage-entries';
        $vars['projectExtra'] = $this->requestedProject->getProjectExtra();
        $vars['projectStats'] = $this->requestedProject->getProjectStats();

        $vars['projectSlug'] = $this->requestedProject->slug;
        $vars['entries_limits_max'] = config('ec5Limits.entries_limits_max');
        $vars['can_bulk_upload_enums'] = config('ec5Enums.can_bulk_upload');
        $vars['can_bulk_upload'] = $this->requestedProject->getCanBulkUpload();
        return view('project.project_details', $vars);
    }

    /**
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param EntryLimitsValidator $entryLimitsValidator
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, ApiResponse $apiResponse, EntryLimitsValidator $entryLimitsValidator)
    {
        if (!$this->requestedProjectRole->canEditProject()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        // Get request data
        $input = $request->all();
        // unset the csrf token
        unset($input['_token']);

        // Validate
        if (count($input) > 0) {

            foreach ($input as $ref => $data) {

                // If no limit set, remove and skip
                if (!isset($data['limit']) || $data['limit'] != 1) {
                    unset($input[$ref]);
                    continue;
                }
                // Validate each set of ref limits
                $entryLimitsValidator->validate($data);
                if ($entryLimitsValidator->hasErrors()) {
                    // Otherwise error back and prompt user to add new
                    if ($request->ajax()) {
                        return $apiResponse->errorResponse(400, $entryLimitsValidator->errors());
                    }
                    return Redirect::back()->withErrors($entryLimitsValidator->errors());
                }

                // Additional check
                $entryLimitsValidator->additionalChecks($this->requestedProject, $ref, $data);
                if ($entryLimitsValidator->hasErrors()) {
                    // Otherwise error back and prompt user to add new
                    if ($request->ajax()) {
                        return $apiResponse->errorResponse(400, $entryLimitsValidator->errors());
                    }
                    return Redirect::back()->withErrors($entryLimitsValidator->errors());
                }
            }
        }

        // Add to project definitions
        $this->requestedProject->setEntriesLimits($input);

        // Update in db
        if (!$this->updateRepository->updateProjectStructure($this->requestedProject, true)) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['manage-entries' => 'ec5_45']);
            }
            return Redirect::back()->withErrors($entryLimitsValidator->errors());
        }

        // If ajax, return success 200 code
        if ($request->ajax()) {
            return $apiResponse->toJsonResponse(200);
        }

        // Success
        return Redirect::back()->with('message', 'ec5_200');
    }
}
