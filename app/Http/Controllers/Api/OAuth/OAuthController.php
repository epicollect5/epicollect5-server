<?php

namespace ec5\Http\Controllers\Api\OAuth;

use ec5\Http\Controllers\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use Laravel\Passport\TokenRepository;
use Lcobucci\JWT\Parser as JwtParser;
use Log;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;
use League\OAuth2\Server\AuthorizationServer;
use Exception;
use League\OAuth2\Server\Exception\OAuthServerException;
use ec5\Repositories\QueryBuilder\OAuth\DeleteRepository as OAuthProjectClientDelete;

class OAuthController
{
    use HandlesOAuthErrors;

    /**
     * The authorization server.
     *
     * @var AuthorizationServer
     */
    protected $server;

    /**
     * The token repository instance.
     *
     * @var TokenRepository
     */
    protected $tokens;

    /**
     * The JWT parser instance.
     *
     * @var JwtParser
     */
    protected $jwt;

    /**
     * @var OAuthProjectClientDelete
     */
    protected $oAuthProjectClientDelete;

    /**
     * OAuthController constructor
     *
     * @param AuthorizationServer $server
     * @param TokenRepository $tokens
     * @param JwtParser $jwt
     * @param OAuthProjectClientDelete $oauthProjectClientDelete
     */
    public function __construct(
        AuthorizationServer      $server,
        TokenRepository          $tokens,
        JwtParser                $jwt,
        OAuthProjectClientDelete $oauthProjectClientDelete
    )
    {
        $this->jwt = $jwt;
        $this->server = $server;
        $this->tokens = $tokens;

        $this->oAuthProjectClientDelete = $oauthProjectClientDelete;

    }

    /**
     * Authorize a client to access by issuing an access_token
     *
     * @param ServerRequestInterface $request
     * @param ApiResponse $apiResponse
     * @return JsonResponse|ResponseInterface
     */
    public function issueToken(ServerRequestInterface $request, ApiResponse $apiResponse)
    {

        // Default error code
        $errors['token issue'] = ['ec5_254'];

        try {
            $input = $request->getParsedBody();
            // Revoke all current access tokens for this client
            if (!empty($input['client_id'])) {
                $this->oAuthProjectClientDelete->revokeTokens($input['client_id']);
            }

            // return a new access token
            return $this->server->respondToAccessTokenRequest($request, new Psr7Response);
        } catch (OAuthServerException $e) {

            // Switch on OAuthServerException error type
            switch ($e->getErrorType()) {
                case 'unsupported_grant_type':
                    $errors['token issue'] = ['ec5_261'];
                    break;
                case 'invalid_client':
                    $errors['token issue'] = ['ec5_253'];
                    break;
                default:
                    // Use default error code
            }

        } catch (Exception $e) {
            // Use default error code
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
        }

        return $apiResponse->errorResponse(400, $errors);

    }
}
