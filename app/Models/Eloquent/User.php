<?php

namespace ec5\Models\Eloquent;

use Config;
use ec5\Libraries\Ldap\LdapUser;
use Exception;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use DB;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Log;

class User extends Model implements
    AuthorizableContract,
    CanResetPasswordContract,
    AuthenticatableContract
{
    use Authenticatable, Authorizable, CanResetPassword, HasApiTokens, Notifiable;

    protected $table = 'users';
    protected $fillable = ['name', 'last_name', 'email', 'password', 'avatar', 'state', 'server_role'];

    protected $hidden = ['password', 'remember_token', 'api_token'];

    public function getTotal()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->get();
    }

    public function getYesterday()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::yesterday())
            ->get();
    }

    public function getWeek()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->subWeeks(1))
            ->where($this->table . '.created_at', '<=', Carbon::now()->subDays(1))
            ->get();
    }

    public function getMonth()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfMonth())
            ->get();
    }

    public function getYear()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->where($this->table . '.created_at', '<=', Carbon::now()->endOfYear())
            ->get();
    }

    public function yearByMonth()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('MONTH(created_at) as month'), DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->groupBy('month')
            ->get();
    }

    public function getStats()
    {
        $total = $this->getTotal();
        $today = $this->getYesterday();
        $week = $this->getWeek();
        $month = $this->getMonth();
        $year = $this->getYear();
        $yearByMonth = $this->yearByMonth();

        $distributionByMonth = [];

        foreach ($yearByMonth as $currentMonth) {
            array_push(
                $distributionByMonth,
                array(
                    $this->getMonthName($currentMonth->month) => $currentMonth->users_total
                )
            );
        }

        return array(
            "total" => $total[0]->users_total,
            "today" => $today[0]->users_total,
            "week" => $week[0]->users_total,
            "month" => $month[0]->users_total,
            "year" => $year[0]->users_total,
            "by_month" => $distributionByMonth
        );
    }

    private function getMonthName($monthNumber)
    {
        return date("M", mktime(0, 0, 0, $monthNumber, 1));
    }

    /**
     * Determine if the current user is an admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->server_role === Config::get('ec5Strings.server_roles.admin');
    }

    /**
     * Determine if the current user is a super admin.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->server_role === Config::get('ec5Strings.server_roles.superadmin');
    }

    /**
     * Determine if the current user is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->state === Config::get('ec5Strings.user_state.active');
    }

    public function isUnverified(): bool
    {
        return $this->state === Config::get('ec5Strings.user_state.unverified');
    }

    public function isLocalAndUnverified(): bool
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


    public function createGoogleUser($googleUser)
    {
        $provider = Config::get('ec5Strings.providers.google');
        try {
            DB::beginTransaction();
            $user = new \ec5\Models\Users\User();
            $user->email = $googleUser->email;
            $user->state = Config::get('ec5Strings.user_state.active');
            $user->server_role = Config::get('ec5Strings.server_roles.basic');
            $user->name = isset($googleUser->user['given_name']) ? $googleUser->user['given_name'] : '';
            $user->last_name = isset($googleUser->user['family_name']) ? $googleUser->user['family_name'] : '';
            $user->avatar = isset($googleUser->avatar) ? $googleUser->avatar : '';
            $user->save();

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

    public function updateAppleUser($name, $lastName, $email, $needProvider): bool
    {
        $provider = Config::get('ec5Strings.providers.apple');
        try {
            $user = $this->where('email', $email)->first();
            $user->name = $name;
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

    public function updateGoogleUserDetails($googleUser): bool
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

    public function updateGoogleUser($googleUser): bool
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
