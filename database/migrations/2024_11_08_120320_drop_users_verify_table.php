<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::drop('users_verify');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //nothing to do as table is not needed
    }
};
