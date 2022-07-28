<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class RemoveDeviceidPlatformFromEntryData extends Migration
{
    /**
     * Run the migrations.
     * Remove device_id and platform properties from the json entry_data
     *
     * @return void
     */
    public function up()
    {
        DB::beginTransaction();
        $output = new ConsoleOutput();

        $tables = ['entries' => 'entry', 'entries_history' => 'entry', 'branch_entries' => 'branch_entry', 'branch_entries_history' => 'branch_entry'];

        foreach ($tables as $table => $entryType) {

            // Select only entries which haven't yet migrated (ie don't have the new 'answers' key)
            $entries = DB::table($table)->select('entry_data', 'id')->get();
            foreach ($entries as $entry) {

                $jsonEntry = json_decode($entry->entry_data, true);

                // Check we have an array and this entry hasn't already been processed
                if (is_array($jsonEntry)) {
                    unset($jsonEntry[$entryType]['device_id']);
                    unset($jsonEntry[$entryType]['platform']);

                    $output->writeln("<info>" . "$table entry: " . $entry->id . PHP_EOL . "</info>");
                    DB::table($table)->where('id', $entry->id)->update(['entry_data' => json_encode($jsonEntry)]);
                }
            }

        }

        DB::commit();

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        DB::beginTransaction();
        $output = new ConsoleOutput();

        $tables = ['entries' => 'entry', 'entries_history' => 'entry', 'branch_entries' => 'branch_entry', 'branch_entries_history' => 'branch_entry'];

        foreach ($tables as $table => $entryType) {

            // Select only entries which have migrated (ie they have the new 'answers' key)
            $entries = DB::table($table)->select('entry_data', 'device_id', 'platform', 'id')->get();
            foreach ($entries as $entry) {
                $jsonEntry = json_decode($entry->entry_data, true);

                if (is_array($jsonEntry)) {
                    $jsonEntry[$entryType]['device_id'] = $entry->device_id;
                    $jsonEntry[$entryType]['platform'] = $entry->platform;

                    $output->writeln("<info>" . "$table entry: " . $entry->id . PHP_EOL . "</info>");
                    DB::table($table)->where('id', $entry->id)->update(['entry_data' => json_encode($jsonEntry)]);
                }
            }
        }

        DB::commit();

    }
}
