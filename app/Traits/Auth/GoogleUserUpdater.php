<?php

namespace ec5\Traits\Auth;

use Log;

trait GoogleUserUpdater
{
    public function updateUserDetails($params, $user)
    {
        $googleUser = $params['user']; //decode to array by passing "true"
        $googleUserFirstName = $googleUser['given_name'];
        $googleUserLastName = $googleUser['family_name'];

        Log::error('Google user', [
            'given_name' => $googleUserFirstName,
            'family_name' => $googleUserLastName,
            '$user->name' => $user->name
        ]);



        //update user name and last name only when they are still placeholders
        if ($user->name === config('ec5Strings.user_placeholder.apple_first_name')) {
            $user->name = $googleUserFirstName;
            $user->last_name = $googleUserLastName;
            $user->save();
        }
        if ($user->name === config('ec5Strings.user_placeholder.passwordless_first_name')) {
            $user->name = $googleUserFirstName;
            $user->last_name = $googleUserLastName;
            $user->save();
        }
    }
}
