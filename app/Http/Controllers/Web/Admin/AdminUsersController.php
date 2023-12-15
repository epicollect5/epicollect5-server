<?php

namespace ec5\Http\Controllers\Web\Admin;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Auth\RuleReset as RulePassword;
use ec5\Http\Validation\Admin\RuleAddUser as AddUserValidator;
use ec5\Models\Eloquent\User;
use ec5\Models\Eloquent\UserProvider;
use ec5\Services\UserService;
use Illuminate\Http\Request;
use Config;

class AdminUsersController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Manage Users Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the management of users from a server administrator.
    |
    */

    /**
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param AddUserValidator $validator
     * @param RulePassword $passwordValidator
     * @return $this|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     *
     * Add a new staff user from the admin panel
     *
     * Staff members are added with email, password and active state
     *
     * A 'local' provider is also added
     *
     * The server role will be `basic`
     */
    public function addUser(Request $request, ApiResponse $apiResponse, AddUserValidator $validator, RulePassword $passwordValidator)
    {
        $inputs = $request->all();

        // Validate the data
        $validator->validate($inputs);
        if ($validator->hasErrors()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $validator->errors());
            }
            return redirect()->back()->withErrors($validator->errors());
        }

        $validator->additionalChecks($inputs['email']);
        if ($validator->hasErrors()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $validator->errors());
            }
            return redirect()->back()->withErrors($validator->errors());
        }

        $passwordValidator->validate($inputs);
        if ($passwordValidator->hasErrors()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $passwordValidator->errors());
            }
            return redirect()->back()->withErrors($passwordValidator->errors());
        }
        //mainly foolish password choices to check here
        $passwordValidator->additionalChecks($inputs, $inputs['email']);
        if ($passwordValidator->hasErrors()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $passwordValidator->errors());
            }
            return redirect()->back()->withErrors($passwordValidator->errors());
        }

        /**
         * if the user exists, just add the local provider
         * otherwise add both user and user provider as "local"
         *
         */

        $email = $inputs['email'];
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = new User();
            $user->name = $inputs['first_name'];
            $user->last_name = $inputs['last_name'];
            $user->email = $email;
            $user->password = bcrypt($inputs['password'], ['rounds' => config('auth.bcrypt_rounds')]);
            $user->state = config('epicollect.strings.user_state.active');
            $user->server_role = config('epicollect.strings.server_roles.basic');
            $user->save();
        }

        //if the user exists but unverified, update existing user
        if ($user->state === config('epicollect.strings.user_state.unverified')) {
            $user->name = $inputs['first_name'];
            $user->last_name = $inputs['last_name'];
            $user->password = bcrypt($inputs['password'], ['rounds' => config('auth.bcrypt_rounds')]);
            $user->save();
        }

        //add local provider
        $userProvider = new UserProvider();
        $userProvider->email = $user->email;
        $userProvider->user_id = $user->id;
        $userProvider->provider = config('epicollect.strings.providers.local');
        $userProvider->save();

        // If successfully created
        if ($user->save()) {
            // If ajax, return success json
            if ($request->ajax()) {
                return $apiResponse->toJsonResponse(200);
            }
            // Redirect back to admin page
            return redirect()->back()->with('message', 'ec5_35');
        }
        if ($request->ajax()) {
            return $apiResponse->errorResponse(400, ['add-user' => ['ec5_376']]);
        }
        // Redirect back to admin page with errors
        return redirect()->back()->withErrors(['ec5_376']);
    }
}
