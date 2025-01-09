<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersPasswordlessTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::create('users_passwordless_web', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('email')->unique('email');
            $table->string('token', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
        });

        Schema::create('users_passwordless_api', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('email')->unique('email');
            $table->string('code', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->tinyInteger('attempts')->default('3');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::drop('users_passwordless_web');
        Schema::drop('users_passwordless_api');
    }
}
