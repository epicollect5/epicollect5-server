<?php

namespace ec5\Traits\Requests;

use \Illuminate\Http\Request;

trait JsonRequest
{
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
