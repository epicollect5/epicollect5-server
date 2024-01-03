<?php

namespace ec5\Exceptions;

use ec5\Traits\Middleware\MiddlewareTools;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Psr\Log\LoggerInterface;
use Illuminate\Session\TokenMismatchException;
use ec5\Exceptions\UserNotVerifiedException;
use Redirect;
use App;

use ec5\Http\Controllers\Api\ApiResponse;

class Handler extends ExceptionHandler
{
    use MiddlewareTools;

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * @var ApiResponse
     */
    protected $apiResponse;

    public function __construct(LoggerInterface $log, ApiResponse $apiResponse)
    {
        $this->apiResponse = $apiResponse;
        parent::__construct(app());
    }

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param \Exception $e
     * @return void
     */
    public function report(Exception $e)
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param Exception $e
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Exception $e)
    {

        // Handle not found exceptions
        if ($e instanceof NotFoundHttpException) {

            //in production, redirect all pages not found to home page
            if (App::environment() === 'production') {
                return redirect()->route('home');
            }

            //in development, return a 422 error for debugging
            return $this->errorResponse($request, 'page not found exception', 'ec5_219', 422);
        }

        // Handle method not allowed exceptions
        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->errorResponse($request, 'method not allowed exception', 'ec5_219', 422);
        }

        // Handle token mismatch exceptions
        if ($e instanceof TokenMismatchException) {
            // todo: redirect to login?
            return $this->errorResponse($request, 'csrf exception', 'ec5_116', 422);
        }

        // Handle user not verified exceptions
        if ($e instanceof UserNotVerifiedException) {
            // todo: redirect to send verification email?
            return $this->errorResponse($request, 'user not verified exception', 'ec5_374', 422);
        }

        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Auth\AuthenticationException $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return $this->errorResponse($request, 'unauthenticated', 'ec5_70', 422);
    }

    /**
     * @param Request $request
     * @param $type
     * @param $errorCode
     * @param $httpStatusCode
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    private function errorResponse(Request $request, $type, $errorCode, $httpStatusCode)
    {

        $errors = [$type => [$errorCode]];

        if ($this->isJsonRequest($request)) {
            return $this->apiResponse->errorResponse($httpStatusCode, $errors);
        }

        if ($errorCode == 'ec5_77') {
            return Redirect::to('login')->withErrors(['ec5_70']);
        }

        return response()->make(view('errors.gen_error')->withErrors([$errorCode]), $httpStatusCode);
    }


}
