<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class HashDeviceId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::beginTransaction();
        $output = new ConsoleOutput();

        // Loop each entry table
        $tables = ['entries', 'entries_history', 'branch_entries', 'branch_entries_history'];
        foreach ($tables as $table) {
            $entries = DB::table($table)->select('device_id', 'id')->get();
            foreach ($entries as $entry) {
                DB::table($table)->where('id', $entry->id)->update(['device_id' => Hash::make($entry->device_id)]);
                $output->writeln("<info>" . 'Hashing $table table entry: ' . $entry->id . PHP_EOL . "</info>");
            }
        }

        DB::commit();
    }

    /**
     * Reverse the migrations.
     * Unset 'title' key from each geojson 'properties' key
     *
     * @return void
     */
    public function down()
    {
        // No way to un-hash
    }
}
