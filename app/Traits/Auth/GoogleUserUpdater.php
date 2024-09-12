<?php

namespace ec5\Traits\Auth;

use ec5\Models\User\UserProvider;
use Log;

trait GoogleUserUpdater
{
    public function updateGoogleUserDetails($params, $user): void
    {
        //add the Google provider so next time no verification is needed
        $userProvider = new UserProvider();
        $userProvider->email = $user->email;
        $userProvider->user_id = $user->id;
        $userProvider->provider = $this->googleProviderLabel;
        $userProvider->save();

        $googleUser = $params['user']; //decode to array by passing "true"
        $googleUserFirstName = $googleUser['given_name'];
        $googleUserLastName = $googleUser['family_name'];

        Log::info('Google user', [
            'given_name' => $googleUserFirstName,
            'family_name' => $googleUserLastName,
            '$user->name' => $user->name
        ]);

        //update username and last name only when they are still placeholders
        if ($user->name === config('epicollect.mappings.user_placeholder.apple_first_name')) {
            $user->name = $googleUserFirstName;
            $user->last_name = $googleUserLastName;
            $user->save();
        }
        if ($user->name === config('epicollect.mappings.user_placeholder.passwordless_first_name')) {
            $user->name = $googleUserFirstName;
            $user->last_name = $googleUserLastName;
            $user->save();
        }
    }
}
