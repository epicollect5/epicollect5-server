<?php

namespace ec5\Http\Controllers\Api;

use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Http\Request;

class ProjectApiControllerBase extends ProjectControllerBase
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

        parent::__construct($request);
    }

}
