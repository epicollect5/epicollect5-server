<?php

namespace ec5\Http\Controllers\Web\Admin;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Controller;
use ec5\Http\Validation\Auth\RuleReset as RulePassword;
use ec5\Http\Validation\Admin\RuleUpdateServerRole as UpdateServerRoleValidator;
use ec5\Http\Validation\Admin\RuleUpdateState as UpdateStateValidator;
use ec5\Http\Validation\Admin\RuleAddUser as AddUserValidator;
use ec5\Http\Validation\ValidationBase;
use ec5\Repositories\Eloquent\User\UserRepository;
use ec5\Models\Users\User;
use ec5\Models\Eloquent\UserProvider;
use Illuminate\Http\Request;
use ec5\Http\Requests;
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
     * @var UserRepository object
     */
    protected $userRepository;

    /**
     * Create a new manager users controller instance.
     * Restricted to admin and superadmin users
     *
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Handle a request to update a user role
     *
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param UpdateServerRoleValidator $validator
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUserServerRole(Request $request, ApiResponse $apiResponse, UpdateServerRoleValidator $validator)
    {
        // Update user 'server_role'
        return $this->updateUserProperty($request, $apiResponse, $validator, Config::get('ec5Strings.server_role'));
    }

    /**
     * Handle a request to update a user state
     *
     * @param  \Illuminate\Http\Request  $request
     * @param ApiResponse $apiResponse
     * @param UpdateStateValidator $validator
     * @return $this|\Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function updateUserState(Request $request, ApiResponse $apiResponse, UpdateStateValidator $validator)
    {
        // Update user 'state'
        return $this->updateUserProperty($request, $apiResponse, $validator, Config::get('ec5Strings.state'));
    }

    /**
     * Function to update a user property
     *
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param ValidationBase $validator
     * @param $property
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    private function updateUserProperty(Request $request, ApiResponse $apiResponse, ValidationBase $validator, $property)
    {
        // Get request data
        $input = $request->all();

        $adminUser = $request->user();

        // Validate the data
        $validator->validate($input);
        if ($validator->hasErrors()) {

            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $validator->errors());
            }
            return redirect()->back()->withErrors($validator->errors());
        }

        // Grab user
        $user = User::where('email', $input['email'])->first();

        // Additional validation
        $validator->additionalChecks($adminUser, $user);
        if ($validator->hasErrors()) {

            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $validator->errors());
            }
            return redirect()->back()->withErrors($validator->errors());
        }

        // Attempt to update the user with supplied field and value
        if ($this->userRepository->updateUserByAdmin($user, $property, $input[$property])) {

            // If ajax, return success 200 code
            if ($request->ajax()) {
                return $apiResponse->toJsonResponse(200);
            }
            // Redirect back to admin page
            return redirect()->back();
        }

        // Retrieve error message
        if ($this->userRepository->hasErrors()) {
            $errors = $this->userRepository->errors();
        } else {
            $errors = ['ec5_49'];
        }

        if ($request->ajax()) {
            return $apiResponse->errorResponse(400, ['update-user-' . $property => $errors, 'adminUser' => $adminUser]);
        }

        // redirect back to admin page
        return redirect()->back()->withErrors($errors);
    }


    /**
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param AddUserValidator $validator
     * @param RulePassword $passwordValidator
     * @return $this|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     *
     * Add a new staff user from admin panel
     *
     * Staff memmber are added with email, password and active state
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
        //mainly stupid password choices to checks here
        $passwordValidator->additionalChecks($inputs, $inputs['email']);
        if ($passwordValidator->hasErrors()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $passwordValidator->errors());
            }
            return redirect()->back()->withErrors($passwordValidator->errors());
        }

        /**
         * if user exists, just add the local provider
         * otherwise add both user and user provider as "local"
         * 
         */

        $email = $inputs['email'];
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user  =  new User();
            $user->name = $inputs['first_name'];
            $user->last_name = $inputs['last_name'];
            $user->email = $email;
            $user->password = bcrypt($inputs['password'], ['rounds' => env('BCRYPT_ROUNDS')]);
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->server_role = Config::get('ec5Strings.server_roles.basic');
            $user->save();
        }

        //if user exists but unverified, update existing user
        if ($user->state  === config('ec5Strings.user_state.unverified')) {
            $user->name = $inputs['first_name'];
            $user->last_name = $inputs['last_name'];
            $user->password = bcrypt($inputs['password'], ['rounds' => env('BCRYPT_ROUNDS')]);
            $user->save();
        }

        //add local provider
        $userProvider = new UserProvider();
        $userProvider->email = $user->email;
        $userProvider->user_id = $user->id;
        $userProvider->provider = config('ec5Strings.providers.local');
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

        if ($this->userRepository->hasErrors()) {
            $errors = $this->userRepository->errors();
        } else {
            $errors = ['ec5_39'];
        }

        if ($request->ajax()) {
            return $apiResponse->errorResponse(400, ['add-user' => $errors]);
        }

        // Redirect back to admin page with errors
        return redirect()->back()->withErrors($errors);
    }

    /**
     * Search for users by email
     *
     * @param Request $request
     * @return array
     */
    public function searchByEmail(Request $request)
    {
        // Get request data
        $input = $request->all();

        $users = $this->userRepository->searchByEmail($input['query']);

        // Return users
        return $users;
    }
}
