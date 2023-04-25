<?php

namespace ec5\Http\Controllers\Api\Auth;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AccountController extends Controller
{
    public function handleDeletionRequest(ApiResponse $apiResponse)
    {
        //get user email
        $email = Auth::user()->email;

        try {
            //send email to system email
            Mail::raw('Account Deletion Request', function ($message) use ($email) {
                $message->from(env('MAIL_FROM_ADDRESS'), 'Epicollect5');
                $message->to(env('SYSTEM_EMAIL'));
                $message->subject('Account Deletion Request');
                $message->setBody('Account Deletion Request by ' . $email);
            });
            //send confirmation email to user
            $htmlBody = '<p>Account Deletion Request accepted.</p>';
            $htmlBody .= '<p>The Epicollect5 team will contact you shortly</p>';
            Mail::raw('Account Deletion Request accepted', function ($message) use ($email, $htmlBody) {
                $message->from(env('MAIL_FROM_ADDRESS'), 'Epicollect5');
                $message->to($email);
                $message->subject('Account Deletion Request accepted');
                $message->setBody($htmlBody, 'text/html');
            });
        } catch (\Exception $e) {
            \Log::error('Error acont deletion request', ['error' => $e->getMessage()]);
            return $apiResponse->errorResponse(400, ['account-deletion' => 'ec5_103']);
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
