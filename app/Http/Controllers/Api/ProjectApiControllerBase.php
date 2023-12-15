<?php

namespace ec5\Http\Controllers\Api;

use Illuminate\Http\Request;

class ProjectApiControllerBase
{

    protected $apiRequest;
    protected $apiResponse;
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * ProjectApiControllerBase constructor.
     *
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     *
     */
    public function __construct(Request $request, ApiRequest $apiRequest, ApiResponse $apiResponse)
    {
        $this->apiRequest = $apiRequest;
        $this->apiResponse = $apiResponse;
    }
}
