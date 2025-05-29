<?php

namespace ec5\Services\User;

use DB;
use ec5\Libraries\Auth\Ldap\LdapUser;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Exception;
use Illuminate\Pagination\Paginator;
use Log;
use Throwable;

class UserService
{
    /**
     * @throws Throwable
     */
    public static function createGoogleUser($googleUser): ?User
    {
        $provider = config('epicollect.strings.providers.google');
        try {
            DB::beginTransaction();
            $user = new User();
            $user->email = $googleUser->email;
            $user->state = config('epicollect.strings.user_state.active');
            $user->server_role = config('epicollect.strings.server_roles.basic');
            $user->name = $googleUser->user['given_name'] ?? '';
            $user->last_name = $googleUser->user['family_name'] ?? '';
            $user->avatar = $googleUser->avatar ?? '';
            $isUserSaved = $user->save();

            //if there is not any user, the provider will be null
            $userProvider = new Userprovider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = $provider;
            $isProviderSaved = $userProvider->save();
            if ($isUserSaved && $isProviderSaved) {
                DB::commit();
                return $user;
            } else {
                throw new Exception('$isUserSaved && $isProviderSaved is false');
            }

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error creating/updating social user after login', ['exception' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * @throws Throwable
     */
    public static function createAppleUser($name, $lastName, $email): ?User
    {
        //create new Apple user
        $provider = config('epicollect.strings.providers.apple');
        try {
            DB::beginTransaction();
            $user = new User();

            $user->name = $name;
            $user->last_name = $lastName;
            $user->email = $email;
            $user->server_role = config('epicollect.strings.server_roles.basic');
            $user->state = config('epicollect.strings.user_state.active');
            $user->save();

            $userProvider = new UserProvider();
            $userProvider->user_id = $user->id;
            $userProvider->email = $user->email;
            $userProvider->provider = $provider;
            $userProvider->save();
            DB::commit();

            return $user;
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error creating Apple user after login', ['exception' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * @throws Throwable
     */
    public static function createPasswordlessUser($email): ?User
    {
        $provider = config('epicollect.strings.providers.passwordless');
        try {
            DB::beginTransaction();
            //create user
            $user = new User();
            $user->name = config('epicollect.mappings.user_placeholder.passwordless_first_name');
            $user->email = $email;
            $user->server_role = config('epicollect.strings.server_roles.basic');
            $user->state = config('epicollect.strings.user_state.active');
            $isUserSaved = $user->save();

            //add passwordless provider
            $userProvider = new UserProvider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = $provider;
            $isProviderSaved = $userProvider->save();

            if ($isUserSaved && $isProviderSaved) {
                DB::commit();
                return $user;
            } else {
                throw new Exception('createPasswordlessUser() transaction failed');
            }
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error creating new passwordless user after login', ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @throws Throwable
     */
    public static function updateUnverifiedPasswordlessUser($user): bool
    {
        $provider = config('epicollect.strings.providers.passwordless');
        try {
            DB::beginTransaction();
            $user->state = config('epicollect.strings.user_state.active');
            //update name if empty
            //happens when users are added to a project before they create an ec5 account
            if ($user->name === '') {
                $user->name = config('epicollect.mappings.user_placeholder.passwordless_first_name');
            }
            $isUserSaved = $user->save();

            //add passwordless provider
            $userProvider = new UserProvider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = $provider;
            $isProviderSaved = $userProvider->save();

            if ($isUserSaved && $isProviderSaved) {
                DB::commit();
                return true;
            } else {
                throw new Exception('updatePasswordlessUser() transaction failed');
            }
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error updating unverified passwordless user after login', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @throws Throwable
     */
    public static function updateGoogleUser($googleUser): bool
    {
        $provider = config('epicollect.strings.providers.google');
        try {
            DB::beginTransaction();
            $user = self::amendUserDetailsGoogle($googleUser);
            $areUserDetailsAmended = $user->save();

            $userProvider = new Userprovider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = $provider;
            $isGoogleProviderSaved = $userProvider->save();

            if ($areUserDetailsAmended && $isGoogleProviderSaved) {
                DB::commit();
                return true;
            } else {
                throw new Exception('updateGoogleUser() failed');
            }

        } catch (Throwable $e) {
            Log::error('Error updating Google user after login', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    public static function updateGoogleUserDetails($googleUser): bool
    {
        try {
            $user = self::amendUserDetailsGoogle($googleUser);
            return $user->save();
        } catch (Throwable $e) {
            Log::error('Error updating Google user details', [
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    /**
     * @throws Throwable
     */
    public static function updateAppleUser($name, $lastName, $email, $needProvider): bool
    {
        $provider = config('epicollect.strings.providers.apple');
        try {
            DB::beginTransaction();
            $user = User::where('email', $email)->first();
            $user->name = $name;
            $user->last_name = $lastName;
            $user->state = config('epicollect.strings.user_state.active');
            $isUserSaved = $user->save();

            if ($needProvider) {
                $userProvider = new Userprovider();
                $userProvider->email = $user->email;
                $userProvider->user_id = $user->id;
                $userProvider->provider = $provider;
                $isProviderSaved = $userProvider->save();

            }
            //conditional login by OpenAI :)
            if ($isUserSaved && (!$needProvider || $isProviderSaved)) {
                DB::commit();
                return true;
            }
            throw new Exception('updateAppleUser failed');

        } catch (Throwable $e) {
            Log::error('Error updating Apple user after login', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    private static function amendUserDetailsGoogle($googleUser): User
    {
        $user = User::where('email', $googleUser->email)->first();
        $user->name = $googleUser->user['given_name'] ?? '';
        $user->last_name = $googleUser->user['family_name'] ?? '';
        $user->avatar = $googleUser->avatar ?? '';
        $user->state = config('epicollect.strings.user_state.active');
        return $user;
    }

    public static function findOrCreateLdapUser(LdapUser $ldapUser): ?User
    {
        // Check if we already have registered
        $user = User::where('email', '=', $ldapUser->getAuthIdentifier())->first();

        if (!$user) {
            // If not, create new
            $user = new User();
            $user->email = $ldapUser->getAuthIdentifier();
            $user->state = config('epicollect.strings.user_state.active');
            $user->server_role = config('epicollect.strings.server_roles.basic');
            $user->save();
        }

        // Check user is active
        if ($user->state === 'active') {
            // Update the user name
            $user->name = $ldapUser->getName();
            $user->last_name = $ldapUser->getLastName();
            $user->update();

            return $user;
        }

        //unverified but not local? This user was added to a project
        //before he/she had an account
        if ($user->state === config('epicollect.strings.user_state.unverified')) {
            if ($user->provider === config('epicollect.strings.providers.local')) {
                //local and unverified -> just return the user for further verification
                return $user;
            }

            //unverified but not local, update user as active
            $user->name = $ldapUser->getName();
            $user->last_name = $ldapUser->getLastName();
            $user->state = config('epicollect.strings.user_state.active');
            $user->update();

            return $user;
        }

        return null;
    }

    public static function getAllUsers($search = '', $filters = array()): Paginator
    {
        $perPage = config('epicollect.limits.users_per_page');
        // retrieve paginated users relative to the search (on name and email)
        // and filter (if applicable), ordered by name
        $users = User::where(function ($query) use ($search) {
            // if you have search criteria, add to where clause
            if (!empty($search)) {
                $query->where('name', 'LIKE', $search . '%')
                    ->orWhere('email', 'LIKE', $search . '%');
            }
        })->where(function ($query) use ($filters) {
            // if you have filter criteria, add to where clause
            if (!empty($filters['server_role'])) {
                $query->where('server_role', '=', $filters['server_role']);
            }
            if (!empty($filters['state'])) {
                $query->where('state', '=', $filters['state']);
            }
        });

        // now paginate users
        return $users->simplePaginate($perPage);
    }

    public static function isAuthenticationDomainAllowed($email): bool
    {
        $allowedDomains = config('auth.auth_allowed_domains');
        //if empty, all domains are allowed
        if (empty($allowedDomains)) {
            return true;
        }

        $emailDomain = explode('@', $email)[1];
        return in_array($emailDomain, $allowedDomains);
    }
}
