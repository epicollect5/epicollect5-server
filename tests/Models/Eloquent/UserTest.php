<?php

namespace Tests\Models\Eloquent;

use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserTest extends TestCase
{
    use DatabaseTransactions;

    protected $faker;
    protected $googleUser;
    protected $appleUser;

    public function setUp():void
    {
        parent::setUp();
    }

    public function test_should_check_for_admin()
    {
        $admin = factory(User::class)->create(['server_role' => config('epicollect.strings.server_roles.admin')]);
        $user = User::where('id', $admin->id)->first();
        $this->assertTrue($user->isAdmin());
    }

    public function test_should_check_for_superadmin()
    {
        $superadmin = factory(User::class)->create(['server_role' => config('epicollect.strings.server_roles.superadmin')]);
        $user = User::where('id', $superadmin->id)->first();
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_should_check_for_active_state()
    {
        $active = factory(User::class)->create(['state' => config('epicollect.strings.user_state.active')]);
        $user = User::where('id', $active->id)->first();
        $this->assertTrue($user->isActive());
    }

    public function test_should_check_for_unverified_state()
    {
        $unverified = factory(User::class)->create(['state' => config('epicollect.strings.user_state.unverified')]);
        $user = User::where('id', $unverified->id)->first();
        $this->assertTrue($user->isUnverified());
    }

    public function test_should_check_for_local_and_unverified_state()
    {
        $unverified = factory(User::class)->create(['state' => config('epicollect.strings.user_state.unverified')]);
        factory(UserProvider::class)->create([
            'user_id' => $unverified->id,
            'email' => $unverified->email,
            'provider' => config('epicollect.strings.providers.local')
        ]);

        $user = User::where('id', $unverified->id)->first();

        $this->assertTrue($user->isLocalAndUnverified());
    }
}