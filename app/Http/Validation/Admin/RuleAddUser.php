<?php

namespace ec5\Http\Validation\Admin;

use ec5\Http\Validation\ValidationBase;
use ec5\Models\Eloquent\UserProvider;


class RuleAddUser extends ValidationBase
{
    protected $rules = [
        'first_name' => 'required|max:100',
        'last_name' => 'required|max:100',
        //uniqueness on email AND provider in users_providers
        'email' => 'required|email|max:255',
        'password' => 'required|confirmed|min:6|max:255'
    ];

    // 'email' => 'unique:users,email_address,NULL,id,account_id,1'

    /**
     * Additional checks
     */
    public function additionalChecks($email)
    {
        $providerLocal = config('ec5Strings.providers.local');

        //email and local provider must be unique 
        if (UserProvider::where('email', $email)->where('provider', $providerLocal)->first()) {
            $this->errors['user'] = ['ec5_375'];
            return false;
        }

        return true;
    }
}
