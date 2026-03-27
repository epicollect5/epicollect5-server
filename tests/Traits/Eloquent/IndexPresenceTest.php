<?php

namespace Tests\Traits\Eloquent;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndexPresenceTest extends TestCase
{
    /**
     * Helper to fetch indexes for a table as arrays of column names.
     * Supports mysql and sqlite. Skips test for other drivers.
     *
     * @param string $table
     * @return array<string, array<int, string>> index_name => [col1, col2, ...]
     */
    private function getTableIndexes(string $table): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $dbName = DB::getDatabaseName();
            $rows = DB::select(
                'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
                [$dbName, $table]
            );
            $indexes = [];
            foreach ($rows as $row) {
                $name = $row->INDEX_NAME;
                $indexes[$name][] = $row->COLUMN_NAME;
            }
            return $indexes;
        }

        if ($driver === 'sqlite') {
            // Use sqlite_master as a whitelist of table/index identifiers rather than
            // interpolating arbitrary values into PRAGMA calls. This prevents injection
            // and is correct for identifier discovery in SQLite.
            // Verify the table exists
            $tableRow = DB::selectOne('SELECT name FROM sqlite_master WHERE type = ? AND name = ?', ['table', $table]);
            if (!$tableRow) {
                return [];
            }

            // Get indexes owned by this table from sqlite_master
            $idxRows = DB::select('SELECT name FROM sqlite_master WHERE type = ? AND tbl_name = ?', ['index', $table]);
            $indexes = [];
            foreach ($idxRows as $idx) {
                $name = $idx->name ?? null;
                if (!$name) {
                    continue;
                }

                // Escape double quotes in the index name for safe interpolation into PRAGMA
                $safeName = str_replace('"', '""', $name);
                $cols = DB::select('PRAGMA index_info("' . $safeName . '")');

                // Safely map possible objects to column names; some pragma variations
                // may return objects without a 'name' property. Coalesce to empty string
                // and filter empties to avoid fatal errors and spurious entries.
                $mapped = array_map(fn ($c) => $c->name ?? '', $cols);
                $mapped = array_values(array_filter($mapped, fn ($col) => $col !== ''));
                $indexes[$name] = $mapped;
            }

            return $indexes;
        }

        $this->markTestSkipped("Index presence assertions are not implemented for DB driver: $driver");
    }

    /**
     * Assert that there is an index covering the required columns (order not enforced).
     *
     * @param array<string, array<int,string>> $indexes
     * @param array<int,string> $requiredColumns
     * @return bool
     */
    private function hasIndexCovering(array $indexes, array $requiredColumns): bool
    {
        $required = array_map('strval', $requiredColumns);
        foreach ($indexes as $cols) {
            $cols = array_map('strval', $cols);
            if (count(array_intersect($required, $cols)) === count($required)) {
                return true;
            }
        }
        return false;
    }

    public function test_entries_table_has_composite_indexes_for_aggregates(): void
    {
        $entriesTable = config('epicollect.tables.entries');
        $indexes = $this->getTableIndexes($entriesTable);

        $this->assertIsArray($indexes, 'Failed to retrieve indexes for entries table');

        $needed = [
            ['project_id', 'form_ref', 'parent_uuid'],
            ['project_id', 'form_ref']
        ];

        $foundAny = false;
        foreach ($needed as $req) {
            if ($this->hasIndexCovering($indexes, $req)) {
                $foundAny = true;
                break;
            }
        }

        $this->assertTrue($foundAny, "Expected an index on $entriesTable covering at least (project_id, form_ref[, parent_uuid]). Available indexes: " . json_encode($indexes));
    }

    public function test_branch_entries_table_has_composite_indexes_for_aggregates(): void
    {
        $branchTable = config('epicollect.tables.branch_entries');
        $indexes = $this->getTableIndexes($branchTable);

        $this->assertIsArray($indexes, 'Failed to retrieve indexes for branch_entries table');

        $neededSets = [
            ['project_id', 'owner_uuid', 'form_ref'],
            ['project_id', 'owner_input_ref', 'owner_uuid']
        ];

        $ok = false;
        foreach ($neededSets as $req) {
            if ($this->hasIndexCovering($indexes, $req)) {
                $ok = true;
                break;
            }
        }

        $this->assertTrue($ok, "Expected an index on $branchTable covering project_id + owner_uuid + form_ref (or owner_input_ref). Available indexes: " . json_encode($indexes));
    }
}
