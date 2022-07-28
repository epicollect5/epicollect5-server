<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TimestampsWithMilliseconds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `entries` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');
        DB::statement('ALTER TABLE `entries` MODIFY `uploaded_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

        DB::statement('ALTER TABLE `entries_history` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');
        DB::statement('ALTER TABLE `entries_history` MODIFY `uploaded_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

        DB::statement('ALTER TABLE `entries_archive` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');
        DB::statement('ALTER TABLE `entries_archive` MODIFY `uploaded_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

        DB::statement('ALTER TABLE `branch_entries` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');
        DB::statement('ALTER TABLE `branch_entries` MODIFY `uploaded_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

        DB::statement('ALTER TABLE `branch_entries_history` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');
        DB::statement('ALTER TABLE `branch_entries_history` MODIFY `uploaded_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

        DB::statement('ALTER TABLE `branch_entries_archive` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');
        DB::statement('ALTER TABLE `branch_entries_archive` MODIFY `uploaded_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');


        DB::statement('ALTER TABLE `password_resets` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

        DB::statement('ALTER TABLE `project_structures` MODIFY `updated_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

        DB::statement('ALTER TABLE `projects` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');
        DB::statement('ALTER TABLE `projects` MODIFY `updated_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

        DB::statement('ALTER TABLE `projects_featured` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');
        DB::statement('ALTER TABLE `projects_featured` MODIFY `updated_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

        DB::statement('ALTER TABLE `users` MODIFY `created_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');
        DB::statement('ALTER TABLE `users` MODIFY `updated_at` TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3) NOT NULL;');

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
