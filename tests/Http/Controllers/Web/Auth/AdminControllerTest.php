<?php

namespace Tests\Http\Controllers\Web\Auth;

use ec5\Mail\UserPasswordlessApiMail;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_renders_correctly()
    {
        $response = $this->get(route('login-admin'));
        $response->assertStatus(200);
    }

    public function test_admin_authenticate_with_valid_credentials()
    {
        Mail::fake();

        $user = factory(User::class)->create([
            'password' => bcrypt('secret'),
            'server_role' => config('epicollect.strings.server_roles.admin'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.local'),
        ]);

        $response = $this->post('login/admin', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('auth.verification_passwordless');
        $response->assertViewHas('email', $user->email);

        Mail::assertSent(UserPasswordlessApiMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_admin_authenticate_with_invalid_password()
    {
        Mail::fake();

        $user = factory(User::class)->create([
            'password' => bcrypt('secret'),
            'server_role' => config('epicollect.strings.server_roles.admin'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.local'),
        ]);

        $response = $this->post('login/admin', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
        Mail::assertNothingSent();
    }

    public function test_admin_authenticate_with_basic_role()
    {
        $user = factory(User::class)->create([
            'password' => bcrypt('secret'),
            'server_role' => config('epicollect.strings.server_roles.basic'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.local'),
        ]);

        $response = $this->post('login/admin', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_admin_authenticate_missing_email()
    {
        $response = $this->post('login/admin', [
            'password' => 'secret',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_admin_authenticate_missing_password()
    {
        $response = $this->post('login/admin', [
            'email' => 'admin@example.com',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_admin_login_sends_verification_email_with_code()
    {
        Mail::fake();

        $user = factory(User::class)->create([
            'password' => bcrypt('secret'),
            'server_role' => config('epicollect.strings.server_roles.admin'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.local'),
        ]);

        $response = $this->post('login/admin', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('auth.verification_passwordless');

        Mail::assertSent(UserPasswordlessApiMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && isset($mail->code);
        });
    }

    public function test_admin_login_stores_verification_token_in_database()
    {
        Mail::fake();

        $user = factory(User::class)->create([
            'password' => bcrypt('secret'),
            'server_role' => config('epicollect.strings.server_roles.admin'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.local'),
        ]);

        $this->post('login/admin', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $this->assertDatabaseHas('users_passwordless_web', [
            'email' => $user->email,
        ]);
    }

    public function test_admin_login_with_superadmin_role_sends_verification_email()
    {
        Mail::fake();

        $user = factory(User::class)->create([
            'password' => bcrypt('secret'),
            'server_role' => config('epicollect.strings.server_roles.superadmin'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.local'),
        ]);

        $response = $this->post('login/admin', [
            'email' => $user->email,
            'password' => 'secret',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('auth.verification_passwordless');

        Mail::assertSent(UserPasswordlessApiMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_admin_login_invalid_credentials_does_not_send_verification_email()
    {
        Mail::fake();

        $user = factory(User::class)->create([
            'password' => bcrypt('secret'),
            'server_role' => config('epicollect.strings.server_roles.admin'),
        ]);
        factory(UserProvider::class)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => config('epicollect.strings.providers.local'),
        ]);

        $this->post('login/admin', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseMissing('users_passwordless_web', [
            'email' => $user->email,
        ]);

        Mail::assertNothingSent();
    }
}
