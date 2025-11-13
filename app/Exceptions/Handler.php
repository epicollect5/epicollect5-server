<?php

namespace ec5\Exceptions;

use ec5\Http\Middleware\PreventRequestsDuringMaintenance;
use ec5\Traits\Middleware\MiddlewareTools;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Session\TokenMismatchException;
use Redirect;
use Throwable;

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


    public function __construct()
    {
        parent::__construct(app());
    }

    /**
     * Convert a validation exception into a JSON response.
     */
    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        return response()->json($exception->errors(), $exception->status);
    }

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @throws Throwable
     */
    public function report(Throwable $e): void
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @throws Throwable
     */
    public function render($request, Throwable $e): \Illuminate\Http\Response|JsonResponse|\Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        /**
         * Handle Maintenance mode  -> HttpException with 503 status code
         * @see PreventRequestsDuringMaintenance
         */
        if ($e instanceof HttpException && $e->getStatusCode() === 503) {
            if (app()->isDownForMaintenance()) {
                if ($this->isJsonRequest($request)) {
                    //let the preflight request go through in maintenance mode (avoid CORS issues)
                    if ($request->isMethod('OPTIONS')) {
                        return response()->json([], 200, []);
                    }

                    //post request from the formbuilder route?
                    if ($request->isMethod('POST')) {
                        // Find the matching route
                        $matchingRoute = Route::getRoutes()->match($request);
                        // Get the route name
                        try {
                            $routeName = $matchingRoute->getName();
                            if ($routeName === 'formbuilder-store') {
                                //tell user to export form(s) and retry later
                                $errors = ['maintenance.mode' => ['ec5_404']];
                                return Response::apiErrorCode(404, $errors);
                            }
                        } catch (Throwable $e) {
                            //if route is not found, ignore and just log
                            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
                        }
                    }
                    //any other json request, standard maintenance message
                    $errors = ['maintenance.mode' => ['ec5_252']];
                    return Response::apiErrorCode(404, $errors);
                }
            }
        }

        // Handle not found exceptions
        if ($e instanceof NotFoundHttpException) {


            //in development, return a 422 error for debugging
            return $this->middlewareErrorResponse($request, 'page not found exception', 'ec5_219', 422);
        }

        // Handle method not allowed exceptions
        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->middlewareErrorResponse($request, 'method not allowed exception', 'ec5_219', 422);
        }

        // Handle token mismatch exceptions
        if ($e instanceof TokenMismatchException) {
            //post request?
            if ($request->isMethod('POST')) {
                // Check if the request is coming from the formbuilder store route
                $routeName = $request->route() ? $request->route()->getName() : null;
                if ($routeName === 'formbuilder-store') {
                    // Return a session expired response for the formbuilder store route, better UX
                    return $this->middlewareErrorResponse($request, 'csrf exception', 'ec5_402', 422);
                }
                if ($routeName === 'post-project-settings') {
                    // Return a session expired response for the project-settings route, better UX
                    return $this->middlewareErrorResponse($request, 'csrf exception', 'ec5_405', 422);
                }
                if ($routeName === 'post-project-details') {
                    // Return a session expired response for the project-details route, better UX
                    return $this->middlewareErrorResponse($request, 'csrf exception', 'ec5_405', 422);
                }
            }
            return $this->middlewareErrorResponse($request, 'csrf exception', 'ec5_116', 422);
        }

        // Handle user not verified exceptions
        if ($e instanceof UserNotVerifiedException) {
            // todo: redirect to send verification email?
            return $this->middlewareErrorResponse($request, 'user not verified exception', 'ec5_374', 422);
        }

        if ($e instanceof ThrottleRequestsException && $e->getStatusCode() === 429) {
            // Find the matching route
            $matchingRoute = Route::getRoutes()->match($request);
            // Get the route name
            try {
                $routeName = $matchingRoute->getName();
                //is it a passwordless attempt from the web?
                if ($routeName === 'passwordless-token-web') {
                    //redirect users to login page with error notification
                    return redirect()->route('login')->withErrors(['ec5_255']);
                }
            } catch (Throwable $e) {
                //if route is not found, ignore and just log
                Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            }

            return $this->middlewareErrorResponse($request, 'rate-limiter', 'ec5_255', 429);
        }

        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response|JsonResponse|RedirectResponse
     * @throws BindingResolutionException
     */
    protected function unauthenticated($request, AuthenticationException $exception): Response|JsonResponse|RedirectResponse
    {
        return $this->middlewareErrorResponse($request, 'unauthenticated', 'ec5_70', 422);
    }

    /**
     * @param Request $request
     * @param $type
     * @param $errorCode
     * @param $httpStatusCode
     * @return Response|JsonResponse|RedirectResponse
     * @throws BindingResolutionException
     */
    private function middlewareErrorResponse(Request $request, $type, $errorCode, $httpStatusCode): \Illuminate\Http\Response|JsonResponse|RedirectResponse
    {
        $errors = [$type => [$errorCode]];

        if ($this->isJsonRequest($request)) {
            return Response::apiErrorCode($httpStatusCode, $errors);
        }

        if ($errorCode == 'ec5_77') {
            return Redirect::to('login')->withErrors(['ec5_70']);
        }

        return response()->make(view('errors.gen_error')->withErrors([$errorCode]), $httpStatusCode);
    }
}
