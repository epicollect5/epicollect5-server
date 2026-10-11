<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Drop the redundant non-unique index_uuid; the unique uuid index covers all uuid lookups.
     */
    public function up(): void
    {
        $entriesTable = config('epicollect.tables.entries');
        $branchTable = config('epicollect.tables.branch_entries');

        Schema::table($entriesTable, function (Blueprint $table) use ($entriesTable) {
            if (Schema::hasIndex($entriesTable, 'index_uuid')) {
                $table->dropIndex('index_uuid');
            }
        });
        Schema::table($branchTable, function (Blueprint $table) use ($branchTable) {
            if (Schema::hasIndex($branchTable, 'index_uuid')) {
                $table->dropIndex('index_uuid');
            }
        });
    }

    public function down(): void
    {
        Schema::table(config('epicollect.tables.entries'), function (Blueprint $table) {
            $table->index('uuid', 'index_uuid');
        });
        Schema::table(config('epicollect.tables.branch_entries'), function (Blueprint $table) {
            $table->index('uuid', 'index_uuid');
        });
    }
};
