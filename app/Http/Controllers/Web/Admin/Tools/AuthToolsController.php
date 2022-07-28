<?php

namespace ec5\Http\Controllers\Web\Admin\Tools;

use ec5\Http\Controllers\Controller;

use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Users\User;
use Illuminate\Http\Request;
use DB;

class AuthToolsController extends Controller
{

    public function show()
    {
        return view('admin.hash_password');
    }

    public function hash(Request $request)
    {

        $password = $request->input('password');
        dd(bcrypt($password, ['rounds' => 12]), bcrypt($password, ['rounds' => 12]), bcrypt($password, ['rounds' => 12]), $password);
        //validate email and password in request

        //check the user does not exist already

        //build model

        //save

        //email password to user (async)

        //return ok view

    }
}
