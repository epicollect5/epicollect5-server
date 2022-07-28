<?php namespace ec5\Http\Controllers\Web\MorePages;

use Illuminate\Http\Request;
use ec5\Http\Controllers\Controller;

class MoreCollectController extends Controller
{
    public function __construct()
    {
    }
    /**
     * Show info page for creating project (available to all users)
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('more-pages.more_collect', []);
    }
}
