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
        //Add new compressed columns
        Schema::table('entries', function (Blueprint $table) {
            $table->longText('entry_data_compressed')->charset('binary'); // LONGBLOB
            $table->longText('entry_data_compressed')->charset('binary'); // LONGBLOB
        });

        //to avoid OOM issues, write 1000 rows at a time lazily
        DB::table('entries')
            ->select(['id', 'entry_data', 'geo_json_data']) // limit selection
            ->lazyById(1000)
            ->each(function ($row) {
                DB::table('entries')
                    ->where('id', $row->id)
                    ->update([
                        'entry_data_compressed' => DB::raw('COMPRESS('.$row->entry_data.')'),
                        'geo_json_data_compressed' => DB::raw('COMPRESS('.$row->geo_json_data.')')
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn([
                'entry_data_compressed',
                'geo_json_data_compressed'
            ]);
        });
    }
};
