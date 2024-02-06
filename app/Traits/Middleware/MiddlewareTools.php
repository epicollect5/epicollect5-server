<?php

namespace ec5\Traits\Middleware;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

trait MiddlewareTools
{
    /**
     * @param Request $request
     * @param $errorCode
     * @param $httpStatusCode
     * @return JsonResponse|RedirectResponse
     */
    public function middlewareErrorResponse(Request $request, $errorCode, $httpStatusCode)
    {
        $errors = ['middleware' => [$errorCode]];
        if ($this->isJsonRequest($request)) {
            return Response::apiErrorCode($httpStatusCode, $errors);
        }
        // Log in error
        if ($errorCode == 'ec5_77' || $errorCode == 'ec5_70') {
            return redirect()->route('login')->withErrors([$errorCode]);
        }
        // Other error
        return redirect()->route('home')->withErrors([$errorCode]);
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function isJsonRequest(Request $request): bool
    {
        if ($request->isJson() || $request->wantsJson() || preg_match('#^/api#', $request->getPathInfo())) {
            return true;
        }
        return false;
    }
}