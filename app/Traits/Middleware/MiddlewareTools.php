<?php

namespace ec5\Traits\Middleware;

use ec5\Http\Controllers\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait MiddlewareTools
{
    /**
     * @param Request $request
     * @param $errorCode
     * @param $httpStatusCode
     * @return JsonResponse|RedirectResponse
     */
    public function errorResponse(Request $request, $errorCode, $httpStatusCode)
    {
        $apiResponse = new ApiResponse();
        $errors = ['middleware' => [$errorCode]];
        if ($this->isJsonRequest($request)) {
            return $apiResponse->errorResponse($httpStatusCode, $errors);
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