<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class RenameBranchEntryJsonKey extends Migration
{
    /**
     * Run the migrations.
     * Rename the 'branch-entry' key to 'branch_entry'
     *
     * @return void
     */
    public function up()
    {
        DB::beginTransaction();
        $output = new ConsoleOutput();

        // Loop each entry table
        $tables = ['branch_entries', 'branch_entries_history'];

        foreach ($tables as $table) {
            $entries = DB::table($table)->select('entry_data', 'id')->get();
            foreach ($entries as $entry) {
                $jsonEntry = json_decode($entry->entry_data, true);
                if (is_array($jsonEntry)) {
                    $jsonEntry['type'] = 'branch_entry';
                    $jsonEntry['branch_entry'] = $jsonEntry['branch-entry'];
                    unset($jsonEntry['branch-entry']);
                    $output->writeln("<info>" . "UP Updating branch-entry to branch_entry ($table) - entry: " . $entry->id . PHP_EOL . "</info>");
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

        // Loop each entry table
        $tables = ['branch_entries', 'branch_entries_history'];

        foreach ($tables as $table) {
            $entries = DB::table($table)->select('entry_data', 'id')->get();
            foreach ($entries as $entry) {
                $jsonEntry = json_decode($entry->entry_data, true);
                if (is_array($jsonEntry)) {
                    $jsonEntry['type'] = 'branch-entry';
                    $jsonEntry['branch-entry'] = $jsonEntry['branch_entry'];
                    unset($jsonEntry['branch_entry']);
                    $output->writeln("<info>" . "DOWN Updating branch_entry to branch-entry ($table) - entry: " . $entry->id . PHP_EOL . "</info>");
                    DB::table($table)->where('id', $entry->id)->update(['entry_data' => json_encode($jsonEntry)]);
                }
            }
        }

        DB::commit();
    }
}
