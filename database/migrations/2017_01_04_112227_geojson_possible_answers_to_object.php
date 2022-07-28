<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class GeojsonPossibleAnswersToObject extends Migration
{

    /**
     * Run the migrations.
     *
     */
    public function up()
    {

        DB::beginTransaction();
        $output = new ConsoleOutput();

        // Loop each entry table
        $tables = ['entries', 'entries_archive', 'entries_history', 'branch_entries', 'branch_entries_archive', 'branch_entries_history'];

        foreach ($tables as $table) {
            $entries = DB::table($table)->select('geo_json_data', 'id')->get();
            foreach ($entries as $entry) {
                $geoJsons = json_decode($entry->geo_json_data, true);
                if (is_array($geoJsons)) {
                    foreach ($geoJsons as $inputRef => $geoJson) {

                        $possibleAnswers = $geoJson['properties']['possible_answers'];
                        $newPossibleAnswers = [];
                        foreach ($possibleAnswers as $possibleAnswer) {

                            // Check not empty
                            if (!empty($possibleAnswer)) {
                                // Set the answer_ref as the key and '1' as the value
                                $newPossibleAnswers[$possibleAnswer] = 1;
                            }

                        }

                        // Replace the possible answers
                        $geoJson['properties']['possible_answers'] = $newPossibleAnswers;
                        $geoJsons[$inputRef] = $geoJson;
                    }
                    $output->writeln("<info>" . "Updating geojson $table entry: " . $entry->id . PHP_EOL . "</info>");
                    DB::table($table)->where('id', $entry->id)->update(['geo_json_data' => json_encode($geoJsons)]);
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
        $tables = ['entries', 'entries_archive', 'entries_history', 'branch_entries', 'branch_entries_archive', 'branch_entries_history'];

        foreach ($tables as $table) {
            $entries = DB::table($table)->select('geo_json_data', 'id')->get();
            foreach ($entries as $entry) {
                $geoJsons = json_decode($entry->geo_json_data, true);
                if (is_array($geoJsons)) {
                    foreach ($geoJsons as $inputRef => $geoJson) {

                        $possibleAnswers = $geoJson['properties']['possible_answers'];
                        $newPossibleAnswers = [];
                        foreach ($possibleAnswers as $possibleAnswer => $value) {

                            // Check not empty
                            if (!empty($possibleAnswer)) {
                                // Set the answer_ref as the value
                                $newPossibleAnswers[] = $possibleAnswer;
                            }
                        }

                        // Replace the possible answers
                        $geoJson['properties']['possible_answers'] = $newPossibleAnswers;
                        $geoJsons[$inputRef] = $geoJson;
                    }
                    $output->writeln("<info>" . "Updating geojson $table entry: " . $entry->id . PHP_EOL . "</info>");
                    DB::table($table)->where('id', $entry->id)->update(['geo_json_data' => json_encode($geoJsons)]);
                }
            }
        }

        DB::commit();
    }
}
