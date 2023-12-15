<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Validation\Project\RuleProjectDefinitionDetails;
use ec5\Http\Validation\Project\RuleSettings;
use ec5\Repositories\QueryBuilder\Project\UpdateRepository as UpdateRep;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as Search;
use ec5\Models\Images\UploadImage;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Redirect;
use ec5\Traits\Requests\RequestAttributes;

class ProjectEditController
{
    use RequestAttributes;

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
     * @param ApiResponse $apiResponse
     * @param RuleSettings $ruleSettings
     * @param RuleProjectDefinitionDetails $ruleProjectDefinitionDetails
     * @param UpdateRep $updateRep
     * @param Search $search
     */
    public function __construct(
        ApiResponse                  $apiResponse,
        RuleSettings                 $ruleSettings,
        RuleProjectDefinitionDetails $ruleProjectDefinitionDetails,
        UpdateRep                    $updateRep,
        Search                       $search)
    {


        $this->apiResponse = $apiResponse;
        $this->ruleProjectDefinitionDetails = $ruleProjectDefinitionDetails;
        $this->ruleSettings = $ruleSettings;
        $this->updateRep = $updateRep;
        $this->search = $search;
        $this->allowedSettingActions = array_keys(config('epicollect.strings.edit_settings'));

    }

    /**
     * Handle all Settings Edit requests
     *
     * @param $slug
     * @param $action
     * @return JsonResponse
     */
    public function settings($slug, $action)
    {
        $this->action = $action;

        if (!$this->request->has($this->action) || !in_array($this->action, $this->allowedSettingActions)) {
            return $this->apiResponse->errorResponse(400, ['errors' => ['ec5_91']]);
        }

        if (!$this->requestedProjectRole()->canEditProject()) {
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
            foreach ($this->allowedSettingActions as $value) {
                $out[$value] = $this->requestedProject()->$value;
            }
            $this->apiResponse->body = $out;
            return $this->apiResponse->toJsonResponse(200);
        } else {
            return $this->apiResponse->errorResponse(400, []);
        }
    }

    /**
     * @param $slug
     * @return Factory|Application|RedirectResponse|View
     */
    public function details($slug)
    {
        $updateProjectStructuresTable = false;
        $errors = ['message' => 'ec5_45'];
        $this->action = last($this->request->segments());

        if (!$this->requestedProjectRole()->canEditProject()) {
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

            $tempInput['logo_url'] = $this->requestedProject()->ref;

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
        return UploadImage::saveImage($this->requestedProject()->ref, $this->request->file('logo_url'), 'logo.jpg', $driver, config('epicollect.media.' . $driver));
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
            config('epicollect.strings.project_status.trashed'),
            config('epicollect.strings.project_status.locked'),
        ];
        $input['status'] = in_array($input['status'], $statuses) ? $input['status'] : config('epicollect.strings.project_status.active');

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
        $this->requestedProject()->updateProjectDetails($input);

        // Update in the database
        return $this->updateRep->updateProject($this->requestedProject(), $input, $updateProjectStructuresTable);
    }

    /**
     * Helper output view
     *
     * @param array $withErrors
     * @param array $withMessage
     */
    private function helperView($withErrors = [], $withMessage = [])
    {
        if (count($withErrors) > 0) {
            return Redirect::back()->withErrors($withErrors);
        } else {
            return Redirect::back()->with($withMessage);
        }
    }
}