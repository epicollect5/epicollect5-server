<?php

namespace Tests\Commands;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemMigrateEntriesJsonTest extends TestCase
{
    use DatabaseTransactions;

    protected string $srcTableEntries = 'entries';
    protected string $dstTableEntriesJson = 'entries_json';
    protected string $srcTableBranchEntries = 'branch_entries';
    protected string $dstTableBranchEntriesJson = 'branch_entries_json';

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table($this->dstTableBranchEntriesJson)->truncate();
        DB::table($this->dstTableEntriesJson)->truncate();
        DB::table($this->srcTableBranchEntries)->truncate();
        DB::table($this->srcTableEntries)->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Seed a few test entries
        DB::table($this->srcTableEntries)->insert([
            [
                'project_id' => 1,
                'uuid' => 'uuid-1',
                'entry_data' => json_encode(['foo' => 'bar']),
                'geo_json_data' => json_encode(['lat' => 1])
            ],
            [
                'project_id' => 1,
                'uuid' => 'uuid-2',
                'entry_data' => json_encode(['baz' => 'qux']),
                'geo_json_data' => null
            ],
        ]);
    }


    public function test_it_does_a_dry_run_without_changing_anything()
    {
        $this->artisan('system:migrate-entries-json --dry-run')
            ->expectsOutput('🔍 DRY RUN MODE - No changes will be made to the database')
            ->assertExitCode(0);

        // Assert nothing changed
        $this->assertEquals(2, DB::table($this->srcTableEntries)->whereNotNull('entry_data')->count());
        $this->assertEquals(0, DB::table($this->dstTableEntriesJson)->count());
    }

    public function test_it_migrates_and_nullifies_source_when_checksums_match()
    {
        $this->artisan('system:migrate-entries-json --limit=10')
            ->expectsOutputToContain('Migrating')
            ->assertExitCode(0);

        // Entries should be copied
        $this->assertEquals(2, DB::table($this->dstTableEntriesJson)->count());

        // Source should be nullified
        $this->assertEquals(0, DB::table($this->srcTableEntries)->whereNotNull('entry_data')->count());

        $dst = DB::table($this->dstTableEntriesJson)->where('entry_id', 1)->first();
        $srcHash = md5(
            json_encode(json_decode(json_encode(['foo' => 'bar']), true)) .
            json_encode(json_decode(json_encode(['lat' => 1]), true))
        );

        $dstHash = md5(
            json_encode(json_decode($dst->entry_data, true)) .
            json_encode(json_decode($dst->geo_json_data, true))
        );
        $this->assertEquals($srcHash, $dstHash);

        $dst = DB::table($this->dstTableEntriesJson)->where('entry_id', 2)->first();
        $srcHash = md5(
            json_encode(json_decode(json_encode(['baz' => 'qux']), true)) .
            json_encode(json_decode(json_encode(null), true))
        );

        $dstHash = md5(
            json_encode(json_decode($dst->entry_data, true)) .
                        ($dst->geo_json_data !== null
                           ? json_encode(json_decode($dst->geo_json_data, true))
                                : 'null')
        );
        $this->assertEquals($srcHash, $dstHash);
    }
}
