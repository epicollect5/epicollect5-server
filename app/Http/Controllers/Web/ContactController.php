<?php namespace ec5\Http\Controllers\Web;

use ec5\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContactController extends Controller
{

    public function __construct()
    {
    }

    /**
     * Show contact page (available to all users)
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('contact', []);
    }
}
