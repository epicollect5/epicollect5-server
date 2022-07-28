<?php

namespace Tests;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use ec5\Models\Users\User;
use Config;
use ec5\Http\Validation\Admin\RuleUpdateState;


class AdminValidatorTest extends TestCase
{

    // use WithoutMiddleware;

    /**
     * Testing update state rules
     */
    public function testUpdateStateValidator()
    {

        $validator = new RuleUpdateState();

        // 1 incorrect state
        $data['email'] = 'j.doe@imperial.ac.uk';
        $data['state'] = 'aaaa';
        $this->validateTrue($validator, $data);

        // 2 email key missing
        $data['state'] = 'aaaa';
        $this->validateTrue($validator, $data);

        // 3 state key missing
        $data['email'] = 'j.doe@imperial.ac.uk';
        $this->validateTrue($validator, $data);

        // 4 all good
        $data['email'] = 'j.doe@imperial.ac.uk';
        $data['state'] = 'active';
        $this->validateFalse($validator, $data);
    }

    /**
     * Testing update state additional rules
     */
    public function testUpdateStateAdditionalValidator()
    {
        $validator = new \ec5\Http\Validation\Admin\RuleUpdateState();
        $serverRoles = Config::get('ec5Permissions.server.roles');

        // Test state being updated by users with various server_roles

        $adminUser = factory(User::class)->create();
        $user = factory(User::class)->create();

        // 1 user can't update their own state
        $this->validateUserCantUpdateThemselves($validator, $adminUser, $adminUser);

        // 2 check each role in permissions file can perform actions on those roles allowed
        foreach ($serverRoles as $adminServerRole => $allowedRoles) {

            $adminUser->server_role = $adminServerRole;

            foreach ($allowedRoles as $userServerRole) {
                $user->server_role = $userServerRole;
                $this->userCanPerformAction($validator, $adminUser, $user);
            }
        }

        // 3 check each role in permissions file cannot perform actions on those roles not allowed
        foreach ($serverRoles as $adminServerRole => $allowedRoles) {

            $adminUser->server_role = $adminServerRole;

            // Get roles that are not in $allowedRoles, ie the roles NOT allowed
            $notAllowedRoles = array_diff(array_keys($serverRoles), $allowedRoles);
            foreach ($notAllowedRoles as $userServerRole) {
                $user->server_role = $userServerRole;
                $this->userCantPerformAction($validator, $adminUser, $user);
            }
        }

        //        // 2 basic users can't update any states
        //        $adminUser->server_role = 'basic';
        //
        //        $user->server_role = 'basic';
        //        $this->validateAdditionalTrue($validator, $adminUser, $user);
        //        $user->server_role = 'admin';
        //        $this->validateAdditionalTrue($validator, $adminUser, $user);
        //        $user->server_role = 'superadmin';
        //        $this->validateAdditionalTrue($validator, $adminUser, $user);
        //
        //        // 3 admins can only update basic users' state
        //        $adminUser->server_role = 'admin';
        //
        //        $user->server_role = 'basic';
        //        $this->validateAdditionalFalse($validator, $adminUser, $user);
        //        $user->server_role = 'admin';
        //        $this->validateAdditionalTrue($validator, $adminUser, $user);
        //        $user->server_role = 'superadmin';
        //        $this->validateAdditionalTrue($validator, $adminUser, $user);
        //
        //        // 4 super admins can update admin and basic users' states
        //        $adminUser->server_role = 'superadmin';
        //
        //        $user->server_role = 'basic';
        //        $this->validateAdditionalFalse($validator, $adminUser, $user);
        //        $user->server_role = 'admin';
        //        $this->validateAdditionalFalse($validator, $adminUser, $user);
        //        $user->server_role = 'superadmin';
        //        $this->validateAdditionalTrue($validator, $adminUser, $user);

    }

    /**
     * Testing update server role rules
     */
    public function testUpdateServerRoleValidator()
    {

        $validator = new \ec5\Http\Validation\Admin\RuleUpdateServerRole();

        // 1 incorrect server_role
        $data['server_role'] = 'superadmin';
        $this->validateTrue($validator, $data);

        // 2 invalid server_role
        $data['server_role'] = 'aaaa';
        $this->validateTrue($validator, $data);

        // 3 state key missing
        $data['server_roles'] = 'basic';
        $this->validateTrue($validator, $data);

        // 4 all good - basic server role
        $data['server_role'] = 'basic';
        $this->validateFalse($validator, $data);

        // 5 all good - admin server role
        $data['server_role'] = 'admin';
        $this->validateFalse($validator, $data);
    }

    /**
     * Testing update server role additional rules
     */
    public function testUpdateServerRoleAdditionalValidator()
    {
        $validator = new \ec5\Http\Validation\Admin\RuleUpdateServerRole();
        $serverRoles = Config::get('ec5Permissions.server.roles');

        // Test server role being updated by users with various server_roles

        $adminUser = factory(User::class)->create();
        $user = factory(User::class)->create();

        // 1 user can't update their own server role
        $this->validateUserCantUpdateThemselves($validator, $adminUser, $adminUser);

        // 2 check each role in permissions file can perform actions on those roles allowed
        foreach ($serverRoles as $adminServerRole => $allowedRoles) {

            $adminUser->server_role = $adminServerRole;

            foreach ($allowedRoles as $userServerRole) {
                $user->server_role = $userServerRole;
                $this->userCanPerformAction($validator, $adminUser, $user);
            }
        }

        // 3 check each role in permissions file cannot perform actions on those roles not allowed
        foreach ($serverRoles as $adminServerRole => $allowedRoles) {

            $adminUser->server_role = $adminServerRole;

            // Get roles that are not in $allowedRoles, ie the roles NOT allowed
            $notAllowedRoles = array_diff(array_keys($serverRoles), $allowedRoles);
            foreach ($notAllowedRoles as $userServerRole) {
                $user->server_role = $userServerRole;
                $this->userCantPerformAction($validator, $adminUser, $user);
            }
        }
    }


    // Common validator assert helper methods

    /**
     * @param $validator
     * @param $data
     */
    private function validateTrue($validator, $data)
    {
        $validator->validate($data);
        $this->assertTrue($validator->hasErrors());
        $validator->resetErrors();
    }

    /**
     * @param $validator
     * @param $data
     */
    private function validateFalse($validator, $data)
    {
        $validator->validate($data);
        $this->assertFalse($validator->hasErrors());
        $validator->resetErrors();
    }

    /**
     * @param $validator
     * @param $user1
     * @param $user2
     */
    private function validateUserCantUpdateThemselves($validator, $user1, $user2)
    {
        $validator->usersCantUpdateThemselves($user1, $user2);
        $this->assertTrue($validator->hasErrors());
        $validator->resetErrors();
    }

    /**
     * @param $validator
     * @param $adminUser
     * @param $user
     */
    private function userCanPerformAction($validator, $adminUser, $user)
    {
        $validator->userCanPerformAction($adminUser, $user);
        $this->assertFalse($validator->hasErrors());
        $validator->resetErrors();
    }

    /**
     * @param $validator
     * @param $adminUser
     * @param $user
     */
    private function userCantPerformAction($validator, $adminUser, $user)
    {
        $validator->userCanPerformAction($adminUser, $user);
        $this->assertTrue($validator->hasErrors());
        $validator->resetErrors();
    }
}
