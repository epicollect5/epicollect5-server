<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\Api\ApiResponse;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravel\Passport\ClientRepository;
use Illuminate\Http\Request;
use Redirect;
use ec5\Repositories\QueryBuilder\OAuth\SearchRepository as OAuthProjectClientSearch;
use ec5\Repositories\QueryBuilder\OAuth\CreateRepository as OAuthProjectClientCreate;
use ec5\Repositories\QueryBuilder\OAuth\DeleteRepository as OAuthProjectClientDelete;
use ec5\Http\Validation\Project\RuleProjectApp as ProjectAppValidator;
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
     * @var OAuthProjectClientSearch
     */
    protected $oAuthProjectClientSearch;

    /**
     * @var OAuthProjectClientCreate
     */
    protected $oAuthProjectClientCreate;

    /**
     * @var OAuthProjectClientDelete
     */
    protected $oAuthProjectClientDelete;

    /**
     * ProjectAppsController constructor
     *
     * @param ClientRepository $clients
     * @param OAuthProjectClientSearch $oauthProjectClientSearch
     * @param OAuthProjectClientCreate $oauthProjectClientCreate
     * @param OAuthProjectClientDelete $oauthProjectClientDelete
     */
    public function __construct(
        ClientRepository         $clients,
        OAuthProjectClientSearch $oauthProjectClientSearch,
        OAuthProjectClientCreate $oauthProjectClientCreate,
        OAuthProjectClientDelete $oauthProjectClientDelete
    )
    {
        $this->clients = $clients;
        $this->oAuthProjectClientSearch = $oauthProjectClientSearch;
        $this->oAuthProjectClientCreate = $oauthProjectClientCreate;
        $this->oAuthProjectClientDelete = $oauthProjectClientDelete;

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
        $vars['apps'] = $this->oAuthProjectClientSearch->projectApps(
            $this->requestedProject()->getId(),
            [
                'oauth_clients.name',
                'oauth_clients.id',
                'oauth_clients.secret',
                'oauth_clients.created_at'
            ]);

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
     * @param ProjectAppValidator $projectAppValidator
     * @param ApiResponse $apiResponse
     * @return Factory|Application|JsonResponse|RedirectResponse|View
     */
    public function store(Request $request, ProjectAppValidator $projectAppValidator, ApiResponse $apiResponse)
    {
        if (!$this->requestedProjectRole()->canEditProject()) {
            $errors = ['ec5_91'];
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['errors' => $errors]);
            }
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        // Get request data
        $input = $request->all();
        // unset the csrf token
        unset($input['_token']);

        // Add the project id to validate
        $input['project_id'] = $this->requestedProject()->getId();
        // Validate
        $projectAppValidator->validate($input);
        if ($projectAppValidator->hasErrors()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $projectAppValidator->errors());
            }
            return Redirect::back()->withErrors($projectAppValidator->errors());
        }

        // Make the client
        $client = $this->clients->create(
            $request->user()->getKey(), $input['application_name'], ''
        )->makeVisible('secret');

        // Add to the project_clients table
        if (!$this->oAuthProjectClientCreate->createOauthProjectClient($this->requestedProject()->getId(), $client->id)) {
            $errors = ['ec5_91'];
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['errors' => $errors]);
            }
            return Redirect::back()->withErrors(['errors' => $errors]);
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
            $errors = ['ec5_91'];
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['errors' => $errors]);
            }
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        // Get request data
        $input = $request->all();
        // Get the client id
        if (empty($input['clientId'])) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_264']]);
        }
        // Delete the app
        $this->oAuthProjectClientDelete->delete($this->requestedProject()->getId(), $input['clientId']);
        // Success
        return Redirect::back()->with('message', 'ec5_259');
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
        $input = $request->all();
        // Get the client id
        if (empty($input['clientId'])) {
            return view('errors.gen_error')->withErrors(['errors' => ['ec5_264']]);
        }
        // Revoke all access tokens
        $this->oAuthProjectClientDelete->revokeTokens($input['clientId']);
        // Success
        return Redirect::back()->with('message', 'ec5_259');
    }
}