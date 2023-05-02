<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Controller;
use ec5\Mail\UserAccountDeletionAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use ec5\Mail\UserAccountDeletionUser;

class AccountController extends Controller
{
    public function handleDeletionRequest(ApiResponse $apiResponse)
    {
        //get user email
        $email = Auth::user()->email;

        //send confirmation email to user
        try {
            Mail::to($email)->send(new UserAccountDeletionUser());
        } catch (Exception $e) {
            return $apiResponse->errorResponse(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }

        //send request to admin
        try {
            Mail::to(env('SYSTEM_EMAIL'))->send(new UserAccountDeletionAdmin($email));
        } catch (Exception $e) {
            return $apiResponse->errorResponse(400, [
                'account-deletion' => ['ec5_103']
            ]);
        }

        if (Mail::failures()) {
            return $apiResponse->errorResponse(400, ['account-deletion' => 'ec5_103']);
        }

        //return a JSON response (request accepted)
        $data = ['id' => 'account-deletion-request', 'accepted' => true];
        $apiResponse->setData($data);

        return $apiResponse->toJsonResponse(200, 0);
    }
}
