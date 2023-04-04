<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAttemptsToUsersPasswordlessWebTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users_passwordless_web', function (Blueprint $table) {
            $table->tinyInteger('attempts')->default('3');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users_passwordless_web', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
}
