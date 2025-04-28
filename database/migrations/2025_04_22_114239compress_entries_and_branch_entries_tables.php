<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Compress entries and branch_entries tables
        DB::statement('ALTER TABLE entries ROW_FORMAT=COMPRESSED, KEY_BLOCK_SIZE=4, ALGORITHM=INPLACE, LOCK=EXCLUSIVE;');
        DB::statement('ALTER TABLE branch_entries ROW_FORMAT=COMPRESSED, KEY_BLOCK_SIZE=4, ALGORITHM=INPLACE, LOCK=EXCLUSIVE;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove compression explicitly by setting ROW_FORMAT=DYNAMIC without KEY_BLOCK_SIZE
        DB::statement('ALTER TABLE entries ROW_FORMAT=DYNAMIC KEY_BLOCK_SIZE=0, ALGORITHM=INPLACE, LOCK=EXCLUSIVE;');
        DB::statement('ALTER TABLE branch_entries ROW_FORMAT=DYNAMIC KEY_BLOCK_SIZE=0, ALGORITHM=INPLACE, LOCK=EXCLUSIVE;');
    }
};
