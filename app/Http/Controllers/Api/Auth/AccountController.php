<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Controller;
use ec5\Mail\UserAccountDeletionAdmin;
use ec5\Mail\UserAccountDeletionConfirmation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use ec5\Mail\UserAccountDeletionUser;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\User;

class AccountController extends Controller
{
    protected $apiResponse;

    function __construct(ApiResponse $apiResponse)
    {
        $this->apiResponse = $apiResponse;
    }

    public function handleDeletionRequest()
    {
        //get user email
        $email = Auth::user()->email;
        //get route name
        $routeName = request()->route()->getName();
        //find any projects the user has a role
        $userId = Auth::user()->id;
        $userProjects = ProjectRole::where('user_id', $userId)->get();

        //request from the web?
        if ($routeName === 'internalAccountDelete') {

            if (sizeof($userProjects) === 0) {
                //user is not a member of any projects so just remove 
                $user = User::where('email', $email)->where('id', $userId);
                $user->delete();

                //log user out
                Auth::logout();
                request()->session()->flush();
                request()->session()->regenerate();

                //send confirmation email to user
                try {
                    Mail::to($email)->send(new UserAccountDeletionConfirmation());
                } catch (Exception $e) {
                    return $this->apiResponse->errorResponse(400, [
                        'account-deletion' => ['ec5_103']
                    ]);
                }

                //return a JSON response (account deleted, will trigger a page refresh)
                $data = ['id' => 'account-deletion-performed', 'deleted' => true];
                $this->apiResponse->setData($data);

                return $this->apiResponse->toJsonResponse(200, 0);
            }
            return $this->sendAccountDeletionEmails($email);
        } else {
            //this is a request from the mobile app
            if (sizeof($userProjects) === 0) {
                //user is not a member of any projects so just remove 
                $user = User::where('email', $email);
                $user->delete();

                //send confirmation email to user
                try {
                    Mail::to($email)->send(new UserAccountDeletionConfirmation());
                } catch (Exception $e) {
                    return $this->apiResponse->errorResponse(400, [
                        'account-deletion' => ['ec5_103']
                    ]);
                }

                //return a JSON response (account deleted, will trigger a logout on the mobile app)
                $data = ['id' => 'account-deletion-performed', 'deleted' => true];
                $this->apiResponse->setData($data);

                return $this->apiResponse->toJsonResponse(200, 0);
            }

            return $this->sendAccountDeletionEmails($email);
        }
    }

    private function sendAccountDeletionEmails($email)
    {
        //send confirmation email to user
        try {
            Mail::to($email)->send(new UserAccountDeletionUser());
        } catch (Exception $e) {
            return $this->apiResponse->errorResponse(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }

        //send request to admin
        try {
            Mail::to(env('SYSTEM_EMAIL'))->send(new UserAccountDeletionAdmin($email));
        } catch (Exception $e) {
            return $this->apiResponse->errorResponse(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }

        if (Mail::failures()) {
            return $this->apiResponse->errorResponse(400, ['account-deletion' => 'ec5_103']);
        }

        //return a JSON response (request accepted)
        $data = ['id' => 'account-deletion-request', 'accepted' => true];
        $this->apiResponse->setData($data);

        return $this->apiResponse->toJsonResponse(200, 0);
    }
}
