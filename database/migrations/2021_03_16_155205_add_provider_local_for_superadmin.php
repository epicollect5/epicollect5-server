<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProviderLocalForSuperadmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //add superadmins
        $superadmins = DB::table('users')
            ->where('server_role', Config::get('ec5Strings.server_roles.superadmin'))->get();
        foreach ($superadmins as $superadmin) {
            DB::table('users_providers')
                ->insert([
                    'user_id' => $superadmin->id,
                    'email' => $superadmin->email,
                    'provider' => Config::get('ec5Strings.providers.local')
                ]);
        }

        //add admins
        $admins = DB::table('users')
            ->where('server_role', Config::get('ec5Strings.server_roles.admin'))->get();
        foreach ($admins as $admin) {
            DB::table('users_providers')
                ->insert([
                    'user_id' => $admin->id,
                    'email' => $admin->email,
                    'provider' => Config::get('ec5Strings.providers.local')
                ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //remove superadmins local provider
        $superadmins = DB::table('users')
            ->where('server_role', Config::get('ec5Strings.server_roles.superadmin'))->get();

        foreach ($superadmins as $superadmin) {
            DB::table('users_providers')
                ->where('email', $superadmin->email)
                ->where('provider', Config::get('ec5Strings.providers.local'))
                ->delete();
        }

        //remove admins local provider
        $admins = DB::table('users')
            ->where('server_role', Config::get('ec5Strings.server_roles.admin'))->get();

        foreach ($admins as $admin) {
            DB::table('users_providers')
                ->where('email', $admin->email)
                ->where('provider', Config::get('ec5Strings.providers.local'))
                ->delete();
        }
    }
}
