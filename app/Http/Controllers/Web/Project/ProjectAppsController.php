<?php /** @noinspection DuplicatedCode */

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Models\Eloquent\OAuthAccessToken;
use ec5\Models\Eloquent\OAuthClientProject;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravel\Passport\ClientRepository;
use Illuminate\Http\Request;
use Redirect;
use ec5\Http\Validation\Project\RuleProjectApp;
use ec5\Traits\Requests\RequestAttributes;
use Throwable;

class ProjectAppsController
{
    use RequestAttributes;

    /**
     * @var ClientRepository
     */
    protected $clients;

    /**
     * ProjectAppsController constructor
     *
     * @param ClientRepository $clients
     */
    public function __construct(ClientRepository $clients)
    {
        $this->clients = $clients;
    }

    /**
     * @param Request $request
     * @return Factory|Application|JsonResponse|View
     * @throws Throwable
     */
    public function show(Request $request)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        $vars['includeTemplate'] = 'apps';
        $vars['action'] = 'apps';
        $vars['apps'] = OAuthClientProject::getApps($this->requestedProject()->getId());
        // If ajax, return rendered html from $ajaxView
        if ($request->ajax()) {
            return response()->json(view('project.developers.apps_table', $vars)->render());
        }
        return view('project.project_details', $vars);
    }

    /**
     * Create a new client app
     *
     * @param Request $request
     * @param RuleProjectApp $ruleProjectApp
     * @param ApiResponse $apiResponse
     * @return Factory|Application|JsonResponse|RedirectResponse|View
     */
    public function store(Request $request, RuleProjectApp $ruleProjectApp, ApiResponse $apiResponse)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['errors' => ['ec5_91']]);
            }
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }

        // Get request data
        $payload = $request->all();
        // unset the csrf token
        unset($payload['_token']);

        // Add the project id to validate
        $payload['project_id'] = $this->requestedProject()->getId();
        // Validate
        $ruleProjectApp->validate($payload);
        if ($ruleProjectApp->hasErrors()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $ruleProjectApp->errors());
            }
            return Redirect::back()->withErrors($ruleProjectApp->errors());
        }

        // Make the client
        $client = $this->clients->create(
            $request->user()->getKey(), $payload['application_name'], ''
        )->makeVisible('secret');

        // Add to the client_projects table
        $clientProject = new OAuthClientProject();
        $clientProject->client_id = $client->id;
        $clientProject->project_id = $this->requestedProject()->getId();
        if (!$clientProject->save()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['errors' => ['ec5_91']]);
            }
            return Redirect::back()->withErrors(['errors' => ['ec5_91']]);
        }

        if ($request->ajax()) {
            return $apiResponse->successResponse(200);
        }
        // Success
        return Redirect::back()->with('message', 'ec5_259');
    }

    /**
     * Delete a client app
     *
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @return Factory|Application|JsonResponse|RedirectResponse|View
     */
    public function delete(Request $request, ApiResponse $apiResponse)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['errors' => ['ec5_91']]);
            }
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        // Get request data
        $payload = $request->all();
        // Get the client id
        if (empty($payload['client_id'])) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_264']]);
        }
        // Delete the app
        if (!OAuthClientProject::removeApp($this->requestedProject()->getId(), $payload['client_id'])) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_240']]);
        }
        // Success
        return Redirect::back()->with('message', 'ec5_399');
    }

    /**
     * Revoke all access tokens for a client app
     *
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @return Factory|Application|JsonResponse|RedirectResponse|View
     */
    public function revoke(Request $request, ApiResponse $apiResponse)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['errors' => ['ec5_91']]);
            }
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_91']]);
        }
        // Get request data
        $payload = $request->all();
        // Get the client id
        if (empty($payload['client_id'])) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_264']]);
        }
        // Revoke all access tokens
        OAuthAccessToken::where('client_id', $payload['client_id'])->delete();
        // Success
        return Redirect::back()->with('message', 'ec5_259');
    }
}