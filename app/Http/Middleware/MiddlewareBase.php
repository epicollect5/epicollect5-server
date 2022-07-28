<?php
namespace ec5\Http\Middleware;

use ec5\Http\Controllers\Api\ApiResponse;
use Illuminate\Http\Request;
use ec5\Traits\Requests\JsonRequest;

abstract class MiddlewareBase
{
    use JsonRequest;

    /**
     * @var ApiResponse
     */
    protected $apiResponse;

    /**
     * MiddlewareBase constructor.
     * @param ApiResponse $apiResponse
     */
    public function __construct(ApiResponse $apiResponse)
    {
        $this->apiResponse = $apiResponse;
    }

    /**
     * @param Request $request
     * @param $errorCode
     * @param $httpStatusCode
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function errorResponse(Request $request, $errorCode, $httpStatusCode)
    {

        $errors = ['middleware' => [$errorCode]];

        if ($this->isJsonRequest($request)) {
            return $this->apiResponse->errorResponse($httpStatusCode, $errors);
        }

        // Log in error
        if ($errorCode == 'ec5_77' || $errorCode == 'ec5_70') {
            return redirect()->route('login')->withErrors([$errorCode]);
        }

        // Other error
        return redirect()->route('home')->withErrors([$errorCode]);
    }
}