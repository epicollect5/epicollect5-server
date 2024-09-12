<?php

namespace ec5\Http\Controllers\Api\OAuth;

use ec5\Models\OAuth\OAuthAccessToken;
use Illuminate\Http\JsonResponse;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use Laravel\Passport\TokenRepository;
use Lcobucci\JWT\Parser as JwtParser;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Response;

//use Zend\Diactoros\Response as Psr7Response;
use Nyholm\Psr7\Response as Psr7Response;
use Throwable;

class OAuthController
{
    use HandlesOAuthErrors;

    protected AuthorizationServer $server;
    protected TokenRepository $tokens;
    protected JwtParser $jwt;

    /**
     * OAuthController constructor
     *
     * @param AuthorizationServer $server
     * @param TokenRepository $tokens
     * @param JwtParser $jwt
     */
    public function __construct(
        AuthorizationServer $server,
        TokenRepository     $tokens,
        JwtParser           $jwt
    ) {
        $this->jwt = $jwt;
        $this->server = $server;
        $this->tokens = $tokens;
    }

    /**
     * Authorize a client to access by issuing an access_token
     *
     * @param ServerRequestInterface $request
     * @return JsonResponse|ResponseInterface
     */
    public function issueToken(ServerRequestInterface $request)
    {
        // Default error code
        $errors['token issue'] = ['ec5_254'];

        try {
            $payload = $request->getParsedBody();
            // Revoke all current access tokens for this client
            if (!empty($payload['client_id'])) {
                OAuthAccessToken::where('client_id', $payload['client_id'])->delete();
            }
            // return a new access token
            return $this->server->respondToAccessTokenRequest($request, new Psr7Response());
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

        } catch (Throwable $e) {
            // Use default error code
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
        }

        return Response::apiErrorCode(400, $errors);
    }
}
