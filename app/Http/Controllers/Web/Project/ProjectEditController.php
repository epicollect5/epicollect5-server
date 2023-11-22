<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;

use ec5\Http\Controllers\Api\ApiResponse;

use ec5\Http\Validation\Project\RuleProjectDefinitionDetails;
use ec5\Http\Validation\Project\RuleSettings;

use ec5\Repositories\QueryBuilder\Project\UpdateRepository as UpdateRep;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as Search;
use ec5\Models\Images\UploadImage;

use Config;
use Uuid;
use Redirect;

class ProjectEditController extends ProjectControllerBase
{

    protected $action = '';
    protected $allowedSettingActions = [];
    protected $request;
    protected $apiResponse;
    protected $search;
    protected $ruleProjectDefinitionDetails;
    protected $ruleSettings;
    protected $updateRep;

    /**
     * ProjectEditController constructor.
     *
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param RuleSettings $ruleSettings
     * @param RuleProjectDefinitionDetails $ruleProjectDefinitionDetails
     * @param UpdateRep $updateRep
     * @param Search $search
     */
    public function __construct(Request                      $request,
                                ApiResponse                  $apiResponse,
                                RuleSettings                 $ruleSettings,
                                RuleProjectDefinitionDetails $ruleProjectDefinitionDetails,
                                UpdateRep                    $updateRep,
                                Search                       $search)
    {

        parent::__construct($request);

        $this->apiResponse = $apiResponse;
        $this->ruleProjectDefinitionDetails = $ruleProjectDefinitionDetails;
        $this->ruleSettings = $ruleSettings;
        $this->updateRep = $updateRep;
        $this->search = $search;
        $this->allowedSettingActions = config('ec5Enums.edit_settings');

    }

    /**
     * Handle all Settings Edit requests
     *
     * @param $slug
     * @param $action
     * @return \Illuminate\Http\JsonResponse
     */
    public function settings($action)
    {
        $this->action = $action;

        if (!$this->request->has($this->action) || !in_array($this->action, $this->allowedSettingActions)) {
            return $this->apiResponse->errorResponse(400, ['errors' => ['ec5_91']]);
        }

        if (!$this->requestedProjectRole->canEditProject()) {
            return $this->apiResponse->errorResponse(404, ['errors' => ['ec5_91']]);
        }

        $input[$this->action] = $this->request->get($this->action);

        $this->ruleSettings->validate($input, $check_keys = false);

        // If fails return
        if ($this->ruleSettings->hasErrors()) {
            return $this->apiResponse->errorResponse(400, ['errors' => $this->ruleSettings->errors()]);
        }

        if ($this->action == 'status') {
            $input = $this->statusInput($input);
        }

        $done = $this->doUpdate($input);

        if ($done) {
            $out = [];
            foreach ($this->allowedSettingActions as $key => $value) {
                $out[$value] = $this->requestedProject->$value;
            }
            $this->apiResponse->body = $out;
            return $this->apiResponse->toJsonResponse(200);
        } else {
            return $this->apiResponse->errorResponse(400, []);
        }
    }

    /**
     * Handle all details Edit requests
     *
     * @param string $slug project -> slug
     * @return \Illuminate\Http\Response
     */
    /**
     * @param $slug
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|ProjectEditController|\Illuminate\Http\RedirectResponse
     */
    public function details($slug)
    {

        $updateProjectStructuresTable = false;

        $errors = ['message' => 'ec5_45'];

        $this->action = last($this->request->segments());

        if (!$this->requestedProjectRole->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $input = $this->request->all();
        unset($input['_token']);

        // If we have an image, validate the dimensions
        if ($this->request->file('logo_url')) {
            // Get the image width/height
            list($width, $height) = getimagesize($this->request->file('logo_url')->getRealPath());
            // Add to input array to be validated
            $input['logo_width'] = $width;
            $input['logo_height'] = $height;
        }

        $this->ruleProjectDefinitionDetails->validate($input, $check_keys = false);
        if ($this->ruleProjectDefinitionDetails->hasErrors()) {
            return $this->helperView($this->ruleProjectDefinitionDetails->errors());
        }

        $tempInput['small_description'] = $input['small_description'];
        $tempInput['description'] = $input['description'];

        if ($this->request->file('logo_url')) {

            // NOTE: store larger thumb first, then smaller mobile logo
            // As request file gets overwritten with each resize and it's better to shrink than to enlarge
            if (!$this->saveLogos('project_thumb')) {
                return $this->helperView(['message' => 'ec5_83']);
            }

            if (!$this->saveLogos('project_mobile_logo')) {
                return $this->helperView(['message' => 'ec5_83']);
            }

            $tempInput['logo_url'] = $this->requestedProject->ref;

            // We want to trigger an update on the project_structures table,
            // to signal to the app that this project has been updated
            $updateProjectStructuresTable = true;

        }
        $input = $tempInput;

        $updated = $this->doUpdate($input, $updateProjectStructuresTable);

        if ($updated) {
            return $this->helperView([], ['message' => 'ec5_123']);
        }

        if ($this->updateRep->hasErrors()) {
            $errors = $this->updateRep->errors();
        }

        return $this->helperView($errors);

    }

    /**
     * Try and store project logos
     *
     * @param
     * @return boolean
     */
    private function saveLogos($driver)
    {
        return UploadImage::saveImage($this->requestedProject->ref, $this->request->file('logo_url'), 'logo.jpg', $driver, config('ec5Media.' . $driver));

    }

    /**
     * Which status depending on input
     *
     * @param $input
     * @return array
     */
    private function statusInput($input): array
    {
        $statuses = [
            config('ec5Strings.project_status.trashed'),
            config('ec5Strings.project_status.locked'),
        ];
        $input['status'] = in_array($input['status'], $statuses) ? $input['status'] : config('ec5Strings.project_status.active');

        return $input;
    }

    /**
     * Update the project in db
     *
     * @param $input
     * @param bool $updateProjectStructuresTable
     * @return bool
     */
    private function doUpdate($input, $updateProjectStructuresTable = false)
    {
        // Update the Definition and Extra data
        $this->requestedProject->updateProjectDetails($input);

        // Update in the database
        return $this->updateRep->updateProject($this->requestedProject, $input, $updateProjectStructuresTable);
    }

    /**
     * Helper output view
     *
     * @param array $withErrors
     * @param array $withMessage
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\RedirectResponse
     */
    private function helperView($withErrors = [], $withMessage = [])
    {

        $params = $this->defaultProjectDetailsParams('view', 'details-view');
        $params['action'] = $this->action;

        if (count($withErrors) > 0) {
            return Redirect::back()->withErrors($withErrors);
        } else {
            return Redirect::back()->with($withMessage);
        }
    }

}