<?php

namespace ec5\Http\Middleware;

use ec5\DTO\ProjectDTO;
use ec5\Models\OAuth\OAuthClientProject;
use Illuminate\Http\Request;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Log;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;

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
     * ProjectPermissionsApi constructor
     *
     * @param ResourceServer $server
     * @param Request $request
     * @param ProjectDTO $requestedProject
     */
    public function __construct(ResourceServer $server,
                                Request        $request,
                                ProjectDTO     $requestedProject
    )
    {
        $this->server = $server;

        parent::__construct($request, $requestedProject);
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
                        // unknown error
                        Log::error(__METHOD__ . ' failed.', [
                            'exception' => $e->getMessage(),
                            'error-type' => $e->getErrorType()
                        ]);
                        $this->error = 'ec5_103';
                }
                return false;
            }

            // Get the client id
            $clientId = $psr->getAttribute('oauth_client_id');
            // Check the project the client is trying to access matches the authorized project
            $doesClientExist = OAuthClientProject::doesExist($clientId, $this->requestedProject->getId());
            if (!$doesClientExist) {
                // Unauthorized error
                Log::error(__METHOD__ . ' failed.', ['exception' => 'ec5_257']);
                $this->error = 'ec5_257';
                return false;
            }
        }
        return true;
    }
}
