<?php

namespace ec5\Models\User;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Model implements
    AuthorizableContract,
    CanResetPasswordContract,
    AuthenticatableContract
{
    use Authenticatable, Authorizable, CanResetPassword, HasApiTokens, Notifiable;

    protected $table = 'users';
    protected $fillable = ['name', 'last_name', 'email', 'password', 'avatar', 'state', 'server_role'];
    protected $hidden = ['password', 'remember_token', 'api_token'];

    public function isAdmin(): bool
    {
        return $this->server_role === config('epicollect.strings.server_roles.admin');
    }

    public function isSuperAdmin(): bool
    {
        return $this->server_role === config('epicollect.strings.server_roles.superadmin');
    }

    public function isActive(): bool
    {
        return $this->state === config('epicollect.strings.user_state.active');
    }

    public function isUnverified(): bool
    {
        return $this->state === config('epicollect.strings.user_state.unverified');
    }

    public function isLocalAndUnverified(): bool
    {
        $localProvider = config('epicollect.strings.providers.local');
        $userProvider = UserProvider::where('email', $this->email)->where('provider', $localProvider)->first();

        if ($userProvider) {
            if ($this->state === config('epicollect.strings.user_state.unverified')) {
                return true;
            }
        }
        return false;
    }
}
