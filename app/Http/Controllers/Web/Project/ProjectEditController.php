<?php

namespace ec5\Http\Controllers\Web\Project;

use DB;
use ec5\Http\Validation\Project\RuleProjectDefinitionDetails;
use ec5\Http\Validation\Project\RuleSettings;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStructure;
use ec5\Services\Media\PhotoSaverService;
use ec5\Traits\Requests\RequestAttributes;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Redirect;
use Request;
use Response;
use Throwable;

class ProjectEditController
{
    use RequestAttributes;

    protected string $action = '';
    protected string|null $slug;
    protected array $allowedSettingActions = [];
    protected Request $request;
    protected RuleProjectDefinitionDetails $ruleProjectDefinitionDetails;
    protected RuleSettings $ruleSettings;

    public function __construct(
        RuleSettings                 $ruleSettings,
        RuleProjectDefinitionDetails $ruleProjectDefinitionDetails
    ) {
        $this->ruleProjectDefinitionDetails = $ruleProjectDefinitionDetails;
        $this->ruleSettings = $ruleSettings;
        $this->allowedSettingActions = array_keys(config('epicollect.strings.edit_settings'));
    }

    /**
     * Handle all Settings Edit requests
     *
     * @param $slug //imp: used for routing segment, DO NOT remove
     * @param $action
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public function settings($slug, $action)
    {
        $this->slug = $slug; //only used for routing imp: do not remove

        if (!request()->route('action') || !in_array($action, $this->allowedSettingActions)) {
            return Response::apiErrorCode(400, ['errors' => ['ec5_29']]);
        }

        if (!$this->requestedProjectRole()->canEditProject()) {
            return Response::apiErrorCode(404, ['errors' => ['ec5_91']]);
        }

        $params[$action] = request()->get($action);
        $this->ruleSettings->validate($params);
        if ($this->ruleSettings->hasErrors()) {
            return Response::apiErrorCode(400, $this->ruleSettings->errors());
        }

        if ($action === config('epicollect.strings.edit_settings.status')) {
            $params = $this->whichStatus($params);
        }

        try {
            DB::beginTransaction();

            //update the project structure objects in memory first
            $this->requestedProject()->updateProjectDetails($params);

            $wasProjectUpdated = Project::where('id', $this->requestedProject()->getId())
                ->update($params);
            $wasProjectStructureUpdated = ProjectStructure::updateStructures($this->requestedProject());

            if ($wasProjectUpdated && $wasProjectStructureUpdated) {
                DB::commit();
                //todo: the following code, no one has any clue about
                $data = [];
                foreach ($this->allowedSettingActions as $value) {
                    $data[$value] = $this->requestedProject()->$value;
                }

                return Response::apiData($data);
            } else {
                throw new Exception('Cannot update project settings');
            }

        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return Response::apiErrorCode(400, ['errors' => ['ec5_45']]);
        }
    }

    /**
     * @param $slug //imp: used for routing segment, DO NOT remove
     * @return Factory|Application|RedirectResponse|View
     * @throws Throwable
     */
    public function updateDetails($slug)
    {
        $this->slug = $slug; //only used for routing imp:do not remove
        $updateStructures = false;
        $this->action = last(request()->segments());

        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => 'ec5_91']);
        }

        $payload = request()->all();

        unset($payload['_token']);

        // If we have an image, validate the dimensions
        if (request()->file('logo_url')) {
            // Get the image width/height
            list($width, $height) = getimagesize(request()->file('logo_url')->getPathname());
            // Add to be validated
            $payload['logo_width'] = $width;
            $payload['logo_height'] = $height;
        }

        $this->ruleProjectDefinitionDetails->validate($payload);
        if ($this->ruleProjectDefinitionDetails->hasErrors()) {
            return Redirect::back()->withErrors(
                $this->ruleProjectDefinitionDetails->errors()
            );
        }

        $params = [
            'small_description' => $payload['small_description'],
            'description' => $payload['description']
        ];

        //save project logo if needed
        if (request()->file('logo_url')) {
            if (!$this->saveLogo('project_thumb')) {
                return Redirect::back()->withErrors(['message' => 'ec5_83']);
            }

            $params['logo_url'] = $this->requestedProject()->ref;
            // We want to trigger an update on the project_structures table,
            // to signal to the app that this project has been updated with a new logo
            $updateStructures = true;
        }

        // Update the Definition and Extra data
        $this->requestedProject()->updateProjectDetails($params);
        // Update in the database
        if (Project::updateAllTables($this->requestedProject(), $params, $updateStructures)) {
            return Redirect::back()->with(['message' => 'ec5_123']);
        } else {
            return Redirect::back()->withErrors(['errors' => ['ec5_104']]);
        }
    }

    /**
     * Try and store project logo
     */
    private function saveLogo($format): bool
    {
        $dimensions = Common::resolveDimensions($format);
        $disk = Common::resolveDisk($format);
        return PhotoSaverService::saveImage(
            $this->requestedProject()->ref,
            $this->requestedProject()->getId(),
            request()->file('logo_url'),
            'logo.jpg',
            $disk,
            $dimensions
        );
    }

    /**
     * Which status depending on params
     */
    private function whichStatus($params): array
    {
        $statuses = [
            config('epicollect.strings.project_status.trashed'),
            config('epicollect.strings.project_status.locked'),
        ];

        $params['status'] = in_array($params['status'], $statuses) ? $params['status'] : config('epicollect.strings.project_status.active');

        return $params;
    }
}
