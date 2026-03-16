<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('
        ALTER TABLE entries 
        ADD INDEX idx_parent_uuid_lookup (project_id, form_ref, parent_uuid),
        ALGORITHM=INPLACE, LOCK=NONE
    ');

        DB::statement('
        ALTER TABLE branch_entries 
        ADD INDEX idx_owner_uuid_lookup (project_id, form_ref, owner_uuid),
        ALGORITHM=INPLACE, LOCK=NONE
    ');
    }

    public function down(): void
    {
        if (Schema::hasIndex('entries', 'idx_parent_uuid_lookup')) {
            DB::statement('ALTER TABLE entries DROP INDEX idx_parent_uuid_lookup');
        }
        if (Schema::hasIndex('branch_entries', 'idx_owner_uuid_lookup')) {
            DB::statement('ALTER TABLE branch_entries DROP INDEX idx_owner_uuid_lookup');
        }
    }
};
