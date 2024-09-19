<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUsersProvidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::create('users_providers', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('user_id');
            $table->string('email');
            $table->string('provider', 200);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });

        DB::update('ALTER TABLE users_providers AUTO_INCREMENT = 1;');

        Schema::table('users_providers', function ($table) {
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('NO ACTION')->onDelete('CASCADE');
        });

        //set all providers as google to new table
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            DB::table('users_providers')
                ->insert([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'provider' => config('epicollect.strings.providers.google'),
                ]);
        }

        //reset all passwords in user table for basic users, keep passwords for other roles
        DB::table('users')
            ->where('server_role', 'basic')
            ->update([
                'password' => ''
            ]);

        //drop provider column from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        //restore provider column
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider', 200);
        });

        //copy providers back to users table
        $providers = DB::table('users_providers')->get();
        foreach ($providers as $provider) {
            DB::table('users')
                ->where('id', $provider->user_id)
                ->update([
                    'provider' => $provider->provider,
                ]);
        }
        Schema::drop('users_providers');
    }
}
