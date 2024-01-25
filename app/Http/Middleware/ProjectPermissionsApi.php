<?php

namespace ec5\Http\Middleware;

use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use ec5\Repositories\QueryBuilder\OAuth\SearchRepository as OAuthProjectClientSearch;
use League\OAuth2\Server\ResourceServer;

use ec5\Models\Projects\Project;

use ec5\Http\Controllers\Api\ApiResponse as ApiResponse;

use Illuminate\Http\Request;

class ProjectPermissionsApi extends RequestAttributesMiddleware
{

    /*
    |--------------------------------------------------------------------------
    | ProjectPermissionsApi Middleware
    |--------------------------------------------------------------------------
    |
    | This middleware handles project requests from applications (clients) via the api.
    |
    */

    /**
     * The Resource Server instance.
     *
     * @var ResourceServer
     */
    private $server;

    /**
     * @var OAuthProjectClientSearch
     */
    protected $oAuthProjectClientSearch;

    /**
     * ProjectPermissionsApi constructor
     *
     * @param ResourceServer $server
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param Project $requestedProject
     * @param OAuthProjectClientSearch $oAuthProjectClientSearch
     */
    public function __construct(ResourceServer           $server,
                                Request                  $request,
                                ApiResponse              $apiResponse,
                                Project                  $requestedProject,
                                OAuthProjectClientSearch $oAuthProjectClientSearch)
    {
        $this->server = $server;
        $this->oAuthProjectClientSearch = $oAuthProjectClientSearch;

        parent::__construct($request, $apiResponse, $requestedProject);
    }

    /**
     * Check the client has permission
     *
     * @return bool
     */
    public function hasPermission(): bool
    {

        // Only need to check for a permission if the project is private
        if ($this->requestedProject->access == config('epicollect.strings.project_access.private')) {

            // Taken from TokenGuard:
            // First, we will convert the Symfony request to a PSR-7 implementation which will
            // be compatible with the base OAuth2 library. The Symfony bridge can perform a
            // conversion for us to a Zend Diactoros implementation of the PSR-7 request.
            $psr = (new DiactorosFactory)->createRequest($this->request);

            try {
                // Attempt to validate the client request
                $psr = $this->server->validateAuthenticatedRequest($psr);
            } catch (OAuthServerException $e) {

                // Switch on OAuthServerException error type
                switch ($e->getErrorType()) {
                    case 'access_denied':
                        $this->error = 'ec5_256';
                        break;
                    default:
                        // Unauthorized error
                        $this->error = 'ec5_257';
                }
                return false;
            }

            // Get the client id
            $clientId = $psr->getAttribute('oauth_client_id');
            // Check the project the client is trying to access matches the authorized project
            if (!$this->oAuthProjectClientSearch->exists($clientId, $this->requestedProject->getId())) {
                // Unauthorized error
                $this->error = 'ec5_257';
                return false;
            }
        }

        return true;
    }

}
