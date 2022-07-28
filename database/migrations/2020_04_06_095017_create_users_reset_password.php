<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersResetPassword extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users_reset_password', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('user_id')->unique('user_id');
            $table->string('token', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');
        });

        //remove Laravel Auth reset table
        Schema::dropIfExists('password_resets');

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users_reset_password');
    }
}
