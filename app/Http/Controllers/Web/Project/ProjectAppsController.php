<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\ProjectControllerBase;
use Laravel\Passport\ClientRepository;
use Illuminate\Http\Request;
use Redirect;
use ec5\Repositories\QueryBuilder\OAuth\SearchRepository as OAuthProjectClientSearch;
use ec5\Repositories\QueryBuilder\OAuth\CreateRepository as OAuthProjectClientCreate;
use ec5\Repositories\QueryBuilder\OAuth\DeleteRepository as OAuthProjectClientDelete;
use ec5\Http\Validation\Project\RuleProjectApp as ProjectAppValidator;

class ProjectAppsController extends ProjectControllerBase
{

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
     * @param Request $request
     * @param ClientRepository $clients
     * @param OAuthProjectClientSearch $oauthProjectClientSearch
     * @param OAuthProjectClientCreate $oauthProjectClientCreate
     * @param OAuthProjectClientDelete $oauthProjectClientDelete
     */
    public function __construct(
        Request $request,
        ClientRepository $clients,
        OAuthProjectClientSearch $oauthProjectClientSearch,
        OAuthProjectClientCreate $oauthProjectClientCreate,
        OAuthProjectClientDelete $oauthProjectClientDelete
    ) {
        $this->clients = $clients;
        $this->oAuthProjectClientSearch = $oauthProjectClientSearch;
        $this->oAuthProjectClientCreate = $oauthProjectClientCreate;
        $this->oAuthProjectClientDelete = $oauthProjectClientDelete;

        parent::__construct($request);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function show(Request $request)
    {

        if (!$this->requestedProjectRole->canEditProject()) {
            $errors = ['ec5_91'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        $params = $this->defaultProjectDetailsParams('apps', 'details-edit');
        $params['action'] = 'apps';
        $params['apps'] = $this->oAuthProjectClientSearch->projectApps($this->requestedProject->getId(), [
            'oauth_clients.name',
            'oauth_clients.id',
            'oauth_clients.secret',
            'oauth_clients.created_at'
        ]);

        // If ajax, return rendered html from $ajaxView
        if ($request->ajax()) {
            return response()->json(view('project.developers.apps_table', $params)->render());
        }

        return view('project.project_details', $params);

    }

    /**
     * Create a new client app
     *
     * @param Request $request
     * @param ProjectAppValidator $projectAppValidator
     * @param ApiResponse $apiResponse
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request, ProjectAppValidator $projectAppValidator, ApiResponse $apiResponse)
    {

        if (!$this->requestedProjectRole->canEditProject()) {
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
        $input['project_id'] = $this->requestedProject->getId();
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
        if (!$this->oAuthProjectClientCreate->createOauthProjectClient($this->requestedProject->getId(), $client->id)) {
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
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse
     */
    public function delete(Request $request, ApiResponse $apiResponse)
    {
        if (!$this->requestedProjectRole->canEditProject()) {
            $errors = ['ec5_91'];
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['errors' => $errors]);
            }
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        // Get request data
        $input = $request->all();

        // Get the client id
        if (!isset($input['clientId']) || empty($input['clientId'])) {
            $errors = ['ec5_264'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        // Delete the app
        $this->oAuthProjectClientDelete->delete($this->requestedProject->getId(), $input['clientId']);

        // Success
        return Redirect::back()->with('message', 'ec5_259');
    }

    /**
     * Revoke all access tokens for a client app
     *
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse
     */
    public function revoke(Request $request, ApiResponse $apiResponse)
    {
        if (!$this->requestedProjectRole->canEditProject()) {
            $errors = ['ec5_91'];
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['errors' => $errors]);
            }
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        // Get request data
        $input = $request->all();

        // Get the client id
        if (!isset($input['clientId']) || empty($input['clientId'])) {
            $errors = ['ec5_264'];
            return view('errors.gen_error')->withErrors(['errors' => $errors]);
        }

        // Revoke all access tokens
        $this->oAuthProjectClientDelete->revokeTokens($input['clientId']);

        // Success
        return Redirect::back()->with('message', 'ec5_259');
    }

}