<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('total_bytes')->default(0)->after('total_entries');
            $table->timestamp('total_bytes_updated_at')->nullable()->after('total_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_stats', function (Blueprint $table) {
            $table->dropColumn('total_bytes');
            $table->dropColumn('total_bytes_updated_at');
        });
    }
};
