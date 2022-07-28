<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersPasswordlessTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users_passwordless_web', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('email')->unique('email');
            $table->string('token', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');
        });

        Schema::create('users_passwordless_api', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('email')->unique('email');
            $table->string('code', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');
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
        Schema::drop('users_passwordless_web');
        Schema::drop('users_passwordless_api');
    }
}
