<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUniqueCompositeKeyUsersProviders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users_providers', function (Blueprint $table) {
            $table->unique(['email', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users_providers', function (Blueprint $table) {
            $table->dropUnique(['email', 'provider']);
        });
    }
}
