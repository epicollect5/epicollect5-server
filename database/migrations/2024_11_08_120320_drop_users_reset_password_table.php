<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('users_reset_password');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //nothing to do as table is not needed
    }
};
