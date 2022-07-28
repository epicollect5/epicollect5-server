<?php namespace ec5\Repositories\Eloquent\User;

use ec5\Models\Users\User;
use Exception;
use ec5\Libraries\Ldap\LdapUser;
use DB;
use Log;
use Config;

trait CreateRepository {

    /**
     * @param $input
     * @return static
     */
    public function create($input)
    {
        return $this->tryUserCreate($input);
    }

    /**
     * @param $input
     * @return static
     */
    private function tryUserCreate($input)
    {
        try {
            $user = User::create($input);
            return $user;
        } catch (Exception $e) {
            Log::error('Cannot create user', ['exception' => $e->getMessage()]);
            $this->errors = ['ec5_39'];
        }
    }


}
