<?php

namespace ec5\Models\Users;

use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Notifications\Notifiable;

use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Auth\Authenticatable;
use ec5\Libraries\Ldap\LdapUser;
use ec5\Models\Eloquent\UserProvider;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use Config;
use DB;
use Exception;
use Log;

class User extends Model implements
    AuthorizableContract,
    CanResetPasswordContract,
    AuthenticatableContract
{
    use Authenticatable, Authorizable, CanResetPassword, HasApiTokens, Notifiable;

    protected $fillable = ['name', 'last_name', 'email', 'password', 'avatar', 'state', 'server_role'];

    protected $hidden = ['password', 'remember_token', 'api_token'];

    /**
     * Determine if the current user is an admin.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->server_role == 'admin';
    }

    /**
     * Determine if the current user is a super admin.
     *
     * @return bool
     */
    public function isSuperAdmin()
    {
        return ($this->server_role == 'superadmin');
    }

    /**
     * Determine if the current user is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->state === Config::get('ec5Strings.user_state.active');
    }

    public function isUnverified()
    {
        return $this->state === Config::get('ec5Strings.user_state.unverified');
    }

    public function isLocalAndUnverified()
    {
        $localProvider = Config::get('ec5Strings.providers.local');
        $userProvider = UserProvider::where('email', $this->email)->where('provider', $localProvider)->first();

        if ($userProvider) {
            if ($this->state === Config::get('ec5Strings.user_state.unverified')) {
                return true;
            }
        }
        return false;
    }

    public function findOrCreateSocialUser($provider, $socialUser)
    {
        // Check if we already have registered
        $user = $this->where('email', '=', $socialUser->email)->first();
        $userProvider = UserProvider::where('email', $socialUser->email)->where('provider', $provider)->first();

        try {
            DB::beginTransaction();
            /**
             * If the user is not found,
             */
            if (!$user) {
                // If not, create new
                $user = new User();
                $user->email = $socialUser->email;
                $user->state = Config::get('ec5Strings.user_state.active');
                $user->server_role = Config::get('ec5Strings.server_roles.basic');
                $user->save();
                DB::commit();


                DB::beginTransaction();
                //if there is not any user, the provider will be null
                $userProvider = new Userprovider();
                $userProvider->email = $user->email;
                $userProvider->user_id = $user->id;
                $userProvider->provider = $provider;
                $userProvider->save();
            }

            //todo if we have a user but no providers found, this was a user added to a project before
            //having an account on epicollect

            //if user is found but not the provider, it means the user is logging in with a different provider but same email.
            if ($user && !$userProvider) {
                //add the new provider account
                $userProvider = new Userprovider();
                $userProvider->email = $user->email;
                $userProvider->user_id = $user->id;
                $userProvider->provider = $provider;
                $userProvider->save();
            }

            // Check user is active.
            if ($user->state === Config::get('ec5Strings.user_state.active')) {
                // Update user name and avatar
                $user->name = isset($socialUser->user['given_name']) ? $socialUser->user['given_name'] : '';
                $user->last_name = isset($socialUser->user['family_name']) ? $socialUser->user['family_name'] : '';
                $user->avatar = isset($socialUser->avatar) ? $socialUser->avatar : '';
                $user->update();

                DB::commit();

                return $user;
            }

            //unverified but not local? This user was added to a project
            //before he/she had an account
            //            if ($user->state === Config::get('ec5Strings.user_state.unverified')) {
            //
            //                //does the user have a local account?
            //                $userProvider = UserProvider::where('email',  $user->email)->where('provider', Config::get('ec5Strings.providers.local'))->first();
            //
            //                if($userProvider){
            //                    //local and unverified just return the user for verification
            //                    return $user;
            //                }
            //
            //                //unverified but not local, update user as active
            //                $user->name = isset($socialUser->user['given_name']) ? $socialUser->user['given_name'] : '';
            //                $user->last_name = isset($socialUser->user['family_name']) ? $socialUser->user['family_name'] : '';
            //                $user->avatar = isset($socialUser->avatar) ? $socialUser->avatar : '';
            //                $user->state  = Config::get('ec5Strings.user_state.active');
            //                $user->update();
            //
            //                DB::commit();
            //                return $user;
            //            }
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating/updating social user after login', ['exception' => $e->getMessage()]);
        }
        return null;
    }

    public function createGoogleUser($googleUser)
    {
        $provider = Config::get('ec5Strings.providers.google');
        try {
            DB::beginTransaction();
            $user = new User();
            $user->email = $googleUser->email;
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->server_role = Config::get('ec5Strings.server_roles.basic');
            $user->name = isset($googleUser->user['given_name']) ? $googleUser->user['given_name'] : '';
            $user->last_name = isset($googleUser->user['family_name']) ? $googleUser->user['family_name'] : '';
            $user->avatar = isset($googleUser->avatar) ? $googleUser->avatar : '';
            $user->save();
            DB::commit();

            DB::beginTransaction();
            //if there is not any user, the provider will be null
            $userProvider = new Userprovider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = $provider;
            $userProvider->save();
            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating/updating social user after login', ['exception' => $e->getMessage()]);
        }
        return null;
    }

    public function createAppleUser($name, $lastName, $email)
    {

        //create new Apple user
        $provider = Config::get('ec5Strings.providers.apple');
        try {
            DB::beginTransaction();
            $user = new User();

            $user->name = $name;
            $user->last_name = $lastName;
            $user->email = $email;
            $user->server_role = Config::get('ec5Strings.server_roles.basic');
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->save();
            DB::commit();

            //create the Apple user provider
            DB::beginTransaction();
            $userProvider = new UserProvider();
            $userProvider->user_id = $user->id;
            $userProvider->email = $user->email;
            $userProvider->provider = $provider;
            $userProvider->save();
            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating Apple user after login', ['exception' => $e->getMessage()]);
        }
        return null;
    }

    public function updateAppleUser($name, $lastName, $email, $needProvider)
    {
        $provider = Config::get('ec5Strings.providers.apple');
        try {
            $user = $this->where('email', $email)->first();
            $user->name =  $name;
            $user->last_name = $lastName;
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->save();

            if ($needProvider) {
                $userProvider = new Userprovider();
                $userProvider->email = $user->email;
                $userProvider->user_id = $user->id;
                $userProvider->provider = $provider;
                $userProvider->save();
            }
        } catch (Exception $e) {
            Log::error('Error updating Apple user after login', ['exception' => $e->getMessage()]);
            return false;
        }

        return true;
    }

    public function updateGoogleUserDetails($googleUser)
    {
        try {
            $user = $this->where('email', $googleUser->email)->first();
            $user->name = isset($googleUser->user['given_name']) ? $googleUser->user['given_name'] : '';
            $user->last_name = isset($googleUser->user['family_name']) ? $googleUser->user['family_name'] : '';
            $user->avatar = isset($googleUser->avatar) ? $googleUser->avatar : '';
            return $user->save();
        } catch (Exception $e) {
            Log::error('Error updating Google user details', [
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }

    public function updateGoogleUser($googleUser)
    {
        $provider = Config::get('ec5Strings.providers.google');
        try {
            DB::beginTransaction();
            $user = $this->where('email', $googleUser->email)->first();
            $user->name = isset($googleUser->user['given_name']) ? $googleUser->user['given_name'] : '';
            $user->last_name = isset($googleUser->user['family_name']) ? $googleUser->user['family_name'] : '';
            $user->avatar = isset($googleUser->avatar) ? $googleUser->avatar : '';
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->save();
            DB::commit();

            //if there is not any user, the provider will be null
            DB::beginTransaction();
            $userProvider = new Userprovider();
            $userProvider->email = $user->email;
            $userProvider->user_id = $user->id;
            $userProvider->provider = $provider;
            $userProvider->save();
            DB::commit();
        } catch (Exception $e) {
            Log::error('Error updating Google user after login', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }

        return true;
    }

    // public function findOrCreateGoogleUser($googleUser)
    // {
    //     // Check if we already have registered
    //     $provider = Config::get('ec5Strings.providers.google');
    //     $user = $this->where('email', '=', $googleUser->email)->first();
    //     $userProvider = UserProvider::where('email', $googleUser->email)->where('provider', $provider)->first();

    //     try {
    //         DB::beginTransaction();

    //         if (!$user) {
    //             $user = new User();
    //             $user->email = $googleUser->email;
    //             $user->state = Config::get('ec5Strings.user_state.active');
    //             $user->server_role = Config::get('ec5Strings.server_roles.basic');
    //             $user->save();
    //             DB::commit();


    //             DB::beginTransaction();
    //             //if there is not any user, the provider will be null
    //             $userProvider = new Userprovider();
    //             $userProvider->email = $user->email;
    //             $userProvider->user_id = $user->id;
    //             $userProvider->provider = $provider;
    //             $userProvider->save();
    //             DB::commit();

    //             return $user;
    //         }


    //         /**
    //          * if we have a user with unverified state,
    //          * it means the user was added to a project
    //          * before having an account.
    //          *
    //          * Update the current user as active
    //          * and add the Google provider
    //          *
    //          * the user gets verified via Google
    //          */
    //         if ($user->state === Config::get('ec5Strings.user_state.unverified')) {
    //             // Update user name and avatar
    //             $user->name = isset($googleUser->user['given_name']) ? $googleUser->user['given_name'] : '';
    //             $user->last_name = isset($googleUser->user['family_name']) ? $googleUser->user['family_name'] : '';
    //             $user->avatar = isset($googleUser->avatar) ? $googleUser->avatar : '';
    //             $user->state = Config::get('ec5Strings.user_state.active');
    //             $user->update();

    //             DB::commit();

    //             return $user;
    //         }


    //         /**
    //          * if the user is active but the Google provider is not found,
    //          * this user created an account with another provider (apple or passwordless)
    //          *
    //          * Ask the user to connect the Google accountform the profile page
    //          * for verification
    //          */
    //         if ($user->state === Config::get('ec5Strings.user_state.active')) {
    //             if (!$userProvider) {
    //                 //the google provider was not found

    //             }
    //         }


    //         //if user is found but not the provider, it means the user is logging in with a different provider but same email.
    //         if ($user && !$userProvider) {
    //             //add the new provider account
    //             $userProvider = new Userprovider();
    //             $userProvider->email = $user->email;
    //             $userProvider->user_id = $user->id;
    //             $userProvider->provider = $provider;
    //             $userProvider->save();
    //         }

    //         // Check user is active.
    //         if ($user->state === Config::get('ec5Strings.user_state.active')) {
    //             // Update user name and avatar
    //             $user->name = isset($googleUser->user['given_name']) ? $googleUser->user['given_name'] : '';
    //             $user->last_name = isset($googleUser->user['family_name']) ? $googleUser->user['family_name'] : '';
    //             $user->avatar = isset($googleUser->avatar) ? $googleUser->avatar : '';
    //             $user->state = Config::get('ec5Strings.user_state.active');
    //             $user->update();

    //             DB::commit();

    //             return $user;
    //         }


    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         Log::error('Error creating/updating social user after login', ['exception' => $e->getMessage()]);
    //     }
    //     return null;
    // }

    public function findLocalUser($providerUser)
    {
        // Check if we already have registered
        $user = $this->where('email', $providerUser->email)
            // ->where('provider', Config::get('ec5Strings.providers.local'))
            ->where('state', Config::get('ec5Strings.user_state.active'))
            ->first();

        if ($user) {
            //we have a user, does the user have a local provider?
            $userProvider = UserProvider::where('email', $providerUser->email)->where('provider', Config::get('ec5Strings.providers.local'))->first();

            if (!$userProvider) {
                return null;
            }
        }

        return $user;
    }

    public function findOrCreateLdapUser(LdapUser $ldapUser)
    {
        // Check if we already have registered
        $user = $this->where('email', '=', $ldapUser->getAuthIdentifier())->first();

        if (!$user) {
            // If not, create new
            $user = new User();
            $user->email = $ldapUser->getAuthIdentifier();
            $user->provider = Config::get('ec5Strings.providers.ldap');
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->server_role = Config::get('ec5Strings.server_roles.basic');
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
        if ($user->state === Config::get('ec5Strings.user_state.unverified')) {
            if ($user->provider === Config::get('ec5Strings.providers.local')) {
                //local and unverified -> just return the user for further verification
                return $user;
            }

            //unverified but not local, update user as active
            $user->name = $ldapUser->getName();
            $user->last_name = $ldapUser->getLastName();
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->update();

            return $user;
        }

        return null;
    }
}
