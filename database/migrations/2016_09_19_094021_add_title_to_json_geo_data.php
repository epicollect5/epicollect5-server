<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;


class AddTitleToJsonGeoData extends Migration
{
    /**
     * Run the migrations.
     * Add 'title' key to each geojson 'properties' key
     * Future entries will have this set on entry upload
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

            $entries = DB::table($table)->select('json_geo_data', 'title', 'id')->get();
            foreach ($entries as $entry) {
                $geoJsons = json_decode($entry->json_geo_data, true);
                if (is_array($geoJsons)) {
                    foreach ($geoJsons as $key => $geoJson) {
                        $geoJson['properties']['title'] = $entry->title;
                        $geoJsons[$key] = $geoJson;
                    }
                    $output->writeln("<info>" . "Updating geojson $table entry: " . $entry->id . PHP_EOL . "</info>");
                    DB::table($table)->where('id', $entry->id)->update(['json_geo_data' => json_encode($geoJsons)]);
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

        // Loop each entry table
        $tables = ['entries', 'entries_history', 'branch_entries', 'branch_entries_history'];

        foreach ($tables as $table) {

            $entries = DB::table($table)->select('json_geo_data', 'id')->get();
            foreach ($entries as $entry) {
                $geoJsons = json_decode($entry->json_geo_data, true);
                if (is_array($geoJsons)) {
                    foreach ($geoJsons as $key => $geoJson) {
                        unset($geoJson['properties']['title']);
                        $geoJsons[$key] = $geoJson;
                    }
                    $output->writeln("<info>" . "Updating geojson $table entry: " . $entry->id . PHP_EOL . "</info>");
                    DB::table($table)->where('id', $entry->id)->update(['json_geo_data' => json_encode($geoJsons)]);
                }
            }

        }

        DB::commit();
    }
}
