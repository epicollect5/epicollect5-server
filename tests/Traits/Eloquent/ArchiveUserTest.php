<?php

namespace Tests\Traits\Eloquent;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ArchiveUserTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_archives_users_without_roles_or_providers()
    {
        $repeatCount = rand(5, 10);
        for ($i = 0; $i < $repeatCount; $i++) {
            // Create a fake user
            $user = factory(User::class)->create();
            //assert user is present before archiving
            $this->assertEquals(1, User::where('id', $user->id)->count());
            // imp: run the archiveUser trait by calling a controller which uses it
            $result = $this->app->call('ec5\Http\Controllers\Api\Auth\AccountController@archiveUser', [
                'email' => $user->email,
                'userId' => $user->id
            ]);
            // Assert that the function returned true
            $this->assertTrue($result);
            //assert the user is archived
            $this->assertEquals(1, User::where('id', $user->id)
                ->where('state', 'archived')
                ->count());

            $this->assertEquals(0, User::where('email', $user->email)
                ->count());
        }
    }

    public function test_it_archives_users_without_roles_but_one_provider()
    {
        $repeatCount = rand(5, 10);
        for ($i = 0; $i < $repeatCount; $i++) {
            // Create a fake user
            $user = factory(User::class)->create();

            //add a random provider for that user
            $provider = array_rand(config('epicollect.strings.providers'));
            factory(UserProvider::class)->create([
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => $provider
            ]);

            //assert user is present before archiving
            $this->assertEquals(1, User::where('id', $user->id)->count());
            // imp: run the archiveUser trait by calling a controller which uses it
            $result = $this->app->call('ec5\Http\Controllers\Api\Auth\AccountController@archiveUser', [
                'email' => $user->email,
                'userId' => $user->id
            ]);
            // Assert that the function returned true
            $this->assertTrue($result);
            //assert the user is archived
            $this->assertEquals(1, User::where('id', $user->id)
                ->where('state', 'archived')
                ->count());

            $this->assertEquals(0, User::where('email', $user->email)
                ->count());

            //assert provider is removed
            $this->assertEquals(0, UserProvider::where('email', $user->email)
                ->where('provider', $provider)
                ->count());
        }
    }

    public function test_it_archives_users_with_roles_and_providers()
    {
        $repeatCount = rand(5, 10);;
        for ($i = 0; $i < $repeatCount; $i++) {
            // Create a fake user
            $user = factory(User::class)->create();

            //create project
            $project = factory(Project::class)->create([
                'created_by' => $user->id
            ]);

            //add role
            factory(ProjectRole::class)->create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'role' => config('epicollect.strings.project_roles.creator')
            ]);


            //add google provider for that user
            factory(UserProvider::class)->create([
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => config('epicollect.strings.providers.google')
            ]);

            //add apple provider as well
            factory(UserProvider::class)->create([
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => config('epicollect.strings.providers.apple')
            ]);

            //assert user is present before archiving
            $this->assertEquals(1, User::where('id', $user->id)->count());
            // imp: run the archiveUser trait by calling a controller which uses it
            $result = $this->app->call('ec5\Http\Controllers\Api\Auth\AccountController@archiveUser', [
                'email' => $user->email,
                'userId' => $user->id
            ]);
            // Assert that the function returned true
            $this->assertTrue($result);
            //assert the user is archived
            $this->assertEquals(1, User::where('id', $user->id)
                ->where('state', 'archived')
                ->count());

            $this->assertEquals(0, User::where('email', $user->email)
                ->count());

            //assert providers are removed
            $this->assertEquals(0, UserProvider::where('email', $user->email)
                ->where('provider', config('epicollect.strings.providers.google'))
                ->count());

            $this->assertEquals(0, UserProvider::where('email', $user->email)
                ->where('provider', config('epicollect.strings.providers.apple'))
                ->count());

            //assert roles are removed
            $this->assertEquals(0, ProjectRole::where('user_id', $user->id)
                ->count());

        }
    }
}
