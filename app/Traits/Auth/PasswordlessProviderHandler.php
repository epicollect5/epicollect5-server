<?php

namespace ec5\Traits\Auth;

use ec5\Models\User\UserProvider;

trait PasswordlessProviderHandler
{
    public function addPasswordlessProviderIfMissing($user, $email): void
    {
        $provider = config('epicollect.strings.providers.passwordless');

        if ($user->state === config('epicollect.strings.user_state.active')) {
            $userProvider = UserProvider::where('email', $email)->where('provider', $provider)->first();

            if (!$userProvider) {
                /**
                 * if the user is active but the passwordless provider is not found,
                 * this user created an account with another provider (Apple or Google or Local)
                 */

                //todo: do nothing aside from adding the passwordless provider?
                //add passwordless provider
                $userProvider = new UserProvider();
                $userProvider->email = $email;
                $userProvider->user_id = $user->id;
                $userProvider->provider = config('epicollect.strings.providers.passwordless');
                $userProvider->save();
            }
        }
    }
}
