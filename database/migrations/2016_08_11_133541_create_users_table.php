<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 100);
            $table->string('last_name', 100);
            $table->string('email')->unique('email');
            $table->string('password');
            $table->string('avatar', 200);
            $table->string('provider', 200);
            $table->string('remember_token', 200);
            $table->enum('server_role', array('basic', 'admin', 'superadmin'))->default('basic');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
            $table->enum('state', array('active', 'disabled'))->default('active');
            $table->string('api_token')->default('');
        });

        // Insert the first superadmin user
        DB::table('users')->insert(
            array(
                'name' => config('epicollect.setup.super_admin_user.first_name'),
                'last_name' => config('epicollect.setup.super_admin_user.last_name'),
                'email' => config('epicollect.setup.super_admin_user.email'),
                'password' => bcrypt(config('epicollect.setup.super_admin_user.password'), ['rounds' => config('auth.bcrypt_rounds')]),
                'server_role' => config('epicollect.strings.server_roles.superadmin'),
                'state' => config('epicollect.strings.user_state.active')
            )
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('users');
    }
}
