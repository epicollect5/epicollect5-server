<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class RestructureEntryObjects extends Migration
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

        $tables = ['entries' => 'entry', 'entries_history' => 'entry', 'branch_entries' => 'branch_entry', 'branch_entries_history' => 'branch_entry'];

        foreach ($tables as $table => $entryType) {

            // Select only entries which haven't yet migrated (ie don't have the new 'answers' key)
            $entries = DB::table($table)->select('entry_data', 'id')->whereRaw('JSON_EXTRACT(entry_data, \'$.' . $entryType . '.answers\') IS NULL')->get();
            foreach ($entries as $entry) {

                $jsonEntry = json_decode($entry->entry_data, true);

                // Check we have an array and this entry hasn't already been processed
                if (is_array($jsonEntry)) {
                    $newEntryObject = [
                        'id' => $jsonEntry['id'],
                        'type' => $jsonEntry['type'],
                        'attributes' => $jsonEntry['attributes'],
                        'relationships' => $jsonEntry['relationships'],
                        $jsonEntry['type'] => [
                            'entry_uuid' => $jsonEntry['entry_uuid'],
                            'title' => $jsonEntry['title'],
                            'platform' => $jsonEntry['platform'] ?? '',
                            'device_id' => Hash::make($jsonEntry['device_id']),
                            'created_at' => $jsonEntry['created_at'],
                            'answers' => $jsonEntry[$jsonEntry['type']],
                            'project_version' => $jsonEntry['structure_last_updated'] ?? '',
                        ]
                    ];
                    $output->writeln("<info>" . "$table entry: " . $entry->id . PHP_EOL . "</info>");
                    DB::table($table)->where('id', $entry->id)->update(['entry_data' => json_encode($newEntryObject)]);
                }
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

        DB::beginTransaction();
        $output = new ConsoleOutput();

        $tables = ['entries' => 'entry', 'entries_history' => 'entry', 'branch_entries' => 'branch_entry', 'branch_entries_history' => 'branch_entry'];

        foreach ($tables as $table => $entryType) {

            // Select only entries which have migrated (ie they have the new 'answers' key)
            $entries = DB::table($table)->select('entry_data', 'id')->whereRaw('JSON_EXTRACT(entry_data, \'$.' . $entryType . '.answers\') IS NOT NULL')->get();
            foreach ($entries as $entry) {
                $jsonEntry = json_decode($entry->entry_data, true);

                if (is_array($jsonEntry)) {
                    $newEntryObject = [
                        'id' => $jsonEntry['id'],
                        'type' => $jsonEntry['type'],
                        'attributes' => $jsonEntry['attributes'],
                        'relationships' => $jsonEntry['relationships'],
                        'entry_uuid' => $jsonEntry[$jsonEntry['type']]['entry_uuid'],
                        'title' => $jsonEntry[$jsonEntry['type']]['title'],
                        'platform' => $jsonEntry[$jsonEntry['type']]['platform'],
                        'device_id' => $jsonEntry[$jsonEntry['type']]['device_id'],
                        'created_at' => $jsonEntry[$jsonEntry['type']]['created_at'],
                        'structure_last_updated' => $jsonEntry[$jsonEntry['type']]['project_version'] ?? '',
                        $jsonEntry['type'] => $jsonEntry[$jsonEntry['type']]['answers']
                    ];
                    $output->writeln("<info>" . "$table entry: " . $entry->id . PHP_EOL . "</info>");
                    DB::table($table)->where('id', $entry->id)->update(['entry_data' => json_encode($newEntryObject)]);
                }
            }
        }

        DB::commit();

    }
}
