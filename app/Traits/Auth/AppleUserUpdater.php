<?php

namespace ec5\Traits\Auth;

use ec5\Models\User\UserProvider;
use Log;
use Throwable;

trait AppleUserUpdater
{
    protected function updateAppleUserDetails($user, $payload): void
    {
        //add the apple provider so next time no verification is needed
        $userProvider = new UserProvider();
        $userProvider->email = $user->email;
        $userProvider->user_id = $user->id;
        $userProvider->provider = config('epicollect.strings.providers.apple');
        $userProvider->save();

        //update user details (if a user object is available)
        try {
            $appleUser = $payload['user']; //decode to array by passing "true"
            $appleUserFirstName = $appleUser['givenName'];
            $appleUserLastName = $appleUser['familyName'];

            //update user name and last name only when they are still placeholders
            if ($user->name === config('epicollect.mappings.user_placeholder.apple_first_name')) {
                $user->name = $appleUserFirstName;
                $user->last_name = $appleUserLastName;
                $user->save();
            }
            if ($user->name === config('epicollect.mappings.user_placeholder.passwordless_first_name')) {
                $user->name = $appleUserFirstName;
                $user->last_name = $appleUserLastName;
                $user->save();
            }
        } catch (Throwable $e) {
            //imp:log in user even if details not updated
            Log::error('Apple user object exception', ['exception' => $e->getMessage()]);
        }
    }
}
