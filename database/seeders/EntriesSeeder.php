<?php

namespace Database\Seeders;


use ec5\Models\Entries\Entry;
use Illuminate\Database\Seeder;

class EntriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //add entries to project with id 7(Bestpint)
        // Specify the number of entries to add
        $numberOfEntries = 1;
        $projectId = 7;//Bestpint

        // Loop to insert the specified number of entries
        for ($i = 0; $i < $numberOfEntries; $i++) {
            factory(Entry::class)->create([
                'project_id' => $projectId,
                'parent_uuid' => '',
                'form_ref' => 'b8a4ac0a586b46dd8ad41ecf9eff39a7_577bc67fe09a3',
                'parent_form_ref' => '',
                'child_counts' => 0//should be zero for last form but does not matter for testing
            ]);
        }
    }
}
