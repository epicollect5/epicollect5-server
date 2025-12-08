<?php

namespace ec5\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * imp: without this middleware, maintenance mode will not work
     *
     */
    protected $except = [
        //
    ];
}
